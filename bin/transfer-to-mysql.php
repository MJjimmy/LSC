<?php
/** One-time CLI-only, maintenance-window MariaDB -> MySQL copy. No source writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
function required(string $key): string {
    $v = getenv($key);
    if ($v === false || $v === '') throw new RuntimeException('Missing transfer configuration: '.$key);
    return $v;
}
function ident(string $s): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $s)) throw new RuntimeException('Unsupported database identifier');
    return '`'.$s.'`';
}
function connectTransfer(string $side, ?string $user=null, ?string $password=null): PDO {
    $host = required('LSC_TRANSFER_'.$side.'_HOST');
    $name = required('LSC_TRANSFER_'.$side.'_DATABASE');
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/D',$host)) throw new RuntimeException('Invalid private database host');
    ident($name);
    $port = getenv('LSC_TRANSFER_'.$side.'_PORT') ?: '3306';
    if (!preg_match('/^[0-9]+$/D', $port) || (int)$port<1 || (int)$port>65535) throw new RuntimeException('Invalid database port');
    $db = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4', $user ?? required('LSC_TRANSFER_'.$side.'_USER'), $password ?? required('LSC_TRANSFER_'.$side.'_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_TIMEOUT=>15]);
    $db->exec("SET time_zone='+00:00'");
    $db->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION'");
    return $db;
}
function catalog(PDO $db): array {
    $rows=$db->query("SELECT TABLE_NAME,TABLE_TYPE,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll();
    $out=[];
    foreach($rows as $r) {
        ident($r['TABLE_NAME']);
        if($r['TABLE_TYPE']!=='BASE TABLE' || strtoupper($r['ENGINE']??'')!=='INNODB') throw new RuntimeException('Only ordinary InnoDB tables are supported');
        $out[]=$r['TABLE_NAME'];
    }
    foreach(['TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $kind=>$schema) {
        if((int)$db->query('SELECT COUNT(*) FROM information_schema.'.$kind.' WHERE '.$schema.'=DATABASE()')->fetchColumn()!==0) throw new RuntimeException('Stored database objects require a separate migration');
    }
    sort($out, SORT_STRING); // Metadata collations differ between MariaDB and MySQL.
    return $out;
}
function metadata(PDO $db,string $table): array {
    $s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$table]);$columns=$s->fetchAll();
    foreach($columns as &$c) {
        ident($c['COLUMN_NAME']);
        $c['COLUMN_TYPE']=preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i','$1',$c['COLUMN_TYPE']);
        $c['EXTRA']=trim(str_ireplace('DEFAULT_GENERATED','',$c['EXTRA']));
        if(is_string($c['COLUMN_DEFAULT'])) {
            $d=$c['COLUMN_DEFAULT'];
            if(strlen($d)>=2 && $d[0]==="'" && substr($d,-1)==="'") $d=str_replace("''","'",substr($d,1,-1));
            if(strtolower($d)==='current_timestamp()') $d='CURRENT_TIMESTAMP';
            if(strtolower($d)==='current_timestamp') $d='CURRENT_TIMESTAMP';
            if(strtoupper($d)==='NULL') $d=null;
            $c['COLUMN_DEFAULT']=$d;
        }
    }unset($c);
    $s=$db->prepare('SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
    $s->execute([$table]);$indexes=$s->fetchAll();
    $s=$db->prepare('SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,k.ORDINAL_POSITION,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION');
    $s->execute([$table]);$foreign=$s->fetchAll();
    // InnoDB treats the omitted NO ACTION and RESTRICT rules identically.
    foreach($foreign as &$fk)foreach(['UPDATE_RULE','DELETE_RULE'] as $rule)if($fk[$rule]==='NO ACTION')$fk[$rule]='RESTRICT';
    unset($fk);
    $pk=[];foreach($indexes as $idx)if($idx['INDEX_NAME']==='PRIMARY')$pk[]=$idx['COLUMN_NAME'];
    if(!$columns || !$pk)throw new RuntimeException('Each table must have columns and a primary key');
    return ['columns'=>$columns,'indexes'=>$indexes,'foreign'=>$foreign,'primary'=>$pk];
}
function selectRows(PDO $db,string $table,array $m): PDOStatement {
    $names=array_column($m['columns'],'COLUMN_NAME');
    return $db->query('SELECT '.implode(',',array_map('ident',$names)).' FROM '.ident($table).' ORDER BY '.implode(',',array_map('ident',$m['primary'])));
}
function fingerprint(PDO $db,string $table,array $m): array {
    $h=hash_init('sha256');$count=0;$s=selectRows($db,$table,$m);
    while($row=$s->fetch(PDO::FETCH_NUM)) {
        hash_update($h,'R');
        foreach($row as $v)hash_update($h,$v===null?'N;':'S'.strlen((string)$v).':'.$v.';');
        $count++;
    }
    $s->closeCursor();return ['rows'=>$count,'sha256'=>hash_final($h)];
}
function verifyForeign(PDO $db,string $table,array $m): void {
    $groups=[];foreach($m['foreign'] as $fk)$groups[$fk['CONSTRAINT_NAME']][]=$fk;
    foreach($groups as $keys) {
        $join=[];$nonnull=[];
        foreach($keys as $k){$join[]='c.'.ident($k['COLUMN_NAME']).'=p.'.ident($k['REFERENCED_COLUMN_NAME']);$nonnull[]='c.'.ident($k['COLUMN_NAME']).' IS NOT NULL';}
        $sql='SELECT COUNT(*) FROM '.ident($table).' c LEFT JOIN '.ident($keys[0]['REFERENCED_TABLE_NAME']).' p ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$nonnull).' AND p.'.ident($keys[0]['REFERENCED_COLUMN_NAME']).' IS NULL';
        if((int)$db->query($sql)->fetchColumn()!==0)throw new RuntimeException('Foreign-key preservation failed for '.$table);
    }
}
$source=null;$target=null;$locked=false;$phase='configuration';
try {
    if(!in_array(getenv('LSC_TRANSFER_CONFIRM'),['copy-preserve-source','inspect-only'],true))throw new RuntimeException('Explicit transfer confirmation is absent');
    $source=connectTransfer('SRC');$target=connectTransfer('DST');
    if(!str_contains(strtolower((string)$source->query('SELECT VERSION()')->fetchColumn()),'mariadb') || !preg_match('/^8\./',(string)$target->query('SELECT VERSION()')->fetchColumn()))throw new RuntimeException('Expected different MariaDB source and MySQL 8 target');
    $tables=catalog($source);$existing=catalog($target);
    if(!in_array('users',$tables,true))throw new RuntimeException('Source is missing the LSC users table');
    if(getenv('LSC_TRANSFER_CONFIRM')==='inspect-only') {
        $summary=[];
        foreach(['source'=>[$source,$tables],'target'=>[$target,$existing]] as $side=>[$db,$names]) {
            $summary[$side]=[];
            foreach($names as $name)$summary[$side][$name]=(int)$db->query('SELECT COUNT(*) FROM '.ident($name))->fetchColumn();
        }
        echo 'LSC_MYSQL_TRANSFER_INSPECTED '.json_encode($summary,JSON_THROW_ON_ERROR).PHP_EOL;
        return;
    }
    if($existing && $existing!==$tables)throw new RuntimeException('Non-empty target has different tables; nothing will be overwritten');
    $appUser=required('LSC_TRANSFER_APP_USER');$appPassword=required('LSC_TRANSFER_APP_PASSWORD');ident($appUser);
    if($appUser==='root')throw new RuntimeException('Application must not use the root account');
    $u=$target->prepare("SELECT COUNT(*) FROM mysql.user WHERE User=? AND Host='%'");$u->execute([$appUser]);$userExists=(int)$u->fetchColumn()>0;
    if($userExists)connectTransfer('DST',$appUser,$appPassword); // Never reset an existing account's password.
    $phase='source-lock';
    $source->exec('LOCK TABLES '.implode(',',array_map(fn($t)=>ident($t).' READ',$tables)));$locked=true;
    $schemas=[];foreach($tables as $t)$schemas[$t]=metadata($source,$t);
    if(!$existing) {
        $target->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach($tables as $t){$phase='schema-'.$t;$ddl=$source->query('SHOW CREATE TABLE '.ident($t))->fetch(PDO::FETCH_NUM)[1];$target->exec($ddl);}
        $target->beginTransaction();
        foreach($tables as $t) {
            $phase='copy-'.$t;$m=$schemas[$t];$names=array_column($m['columns'],'COLUMN_NAME');
            $insert=$target->prepare('INSERT INTO '.ident($t).' ('.implode(',',array_map('ident',$names)).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')');
            $s=selectRows($source,$t,$m);
            while($row=$s->fetch(PDO::FETCH_NUM))$insert->execute($row);
            $s->closeCursor();
        }
        $target->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $verified=[];
    foreach($tables as $t) {
        $phase='verify-'.$t;
        if($schemas[$t]!==metadata($target,$t))throw new RuntimeException('Schema differs; target will not be overwritten: '.$t);
        $a=fingerprint($source,$t,$schemas[$t]);$b=fingerprint($target,$t,$schemas[$t]);
        if($a!==$b)throw new RuntimeException('Data differs; target will not be overwritten: '.$t);
        verifyForeign($target,$t,$schemas[$t]);$verified[$t]=$a;
    }
    if($target->inTransaction())$target->commit();
    $phase='application-account';
    $account=$target->quote($appUser)."@'%'";
    if(!$userExists)$target->exec('CREATE USER '.$account.' IDENTIFIED BY '.$target->quote($appPassword));
    $target->exec('GRANT ALL PRIVILEGES ON '.ident(required('LSC_TRANSFER_DST_DATABASE')).'.* TO '.$account);
    $app=connectTransfer('DST',$appUser,$appPassword);
    foreach($tables as $t)if(fingerprint($app,$t,$schemas[$t])!==$verified[$t])throw new RuntimeException('Application account cannot read preserved data');
    echo 'LSC_MYSQL_TRANSFER_VERIFIED '.json_encode(['tables'=>count($tables),'data'=>$verified,'application_account_verified'=>true,'source_preserved'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {
    if($target instanceof PDO && $target->inTransaction())$target->rollBack();
    $message=$e instanceof PDOException?'Database operation failed (SQLSTATE '.preg_replace('/[^a-zA-Z0-9]/','',(string)$e->getCode()).')':$e->getMessage();
    fwrite(STDERR,'Transfer stopped at '.$phase.': '.$message.'. Source data is unchanged; target may contain incomplete new tables. No automatic overwrite or deletion was performed.'.PHP_EOL);
    exit(1);
} finally {
    if($locked && $source instanceof PDO)$source->exec('UNLOCK TABLES');
}
