<?php
declare(strict_types=1);
namespace LSC\Users;

use PDO;
use RuntimeException;

/** Additive preparation only: never backfill ownership or repair incompatible tables. */
final class EmailVerificationSchema
{
    public static function prepare(PDO $db): void
    {
        $driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver==='sqlite') { self::sqlite($db); return; }
        if ($driver!=='mysql') throw new RuntimeException('Unsupported verification schema driver.');
        $schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();
        if ($schema==='') throw new RuntimeException('Existing schema required.');
        $columns=static function(string $table) use($db,$schema): array {
            $q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_MAXIMUM_LENGTH,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
            $q->execute([$schema,$table]); $out=[];
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) $out[$row['COLUMN_NAME']]=$row;
            return $out;
        };
        $engine=static function(string $table) use($db,$schema): void {
            $q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?'); $q->execute([$schema,$table]);
            if (strtoupper((string)$q->fetchColumn())!=='INNODB') throw new RuntimeException('Verification schema engine mismatch.');
        };
        $users=$columns('users');
        $type=strtolower((string)($users['user_id']['COLUMN_TYPE']??''));
        if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(\d+\))?( unsigned)?$/D',$type)) throw new RuntimeException('Existing user key required.');
        $engine('users');
        if (!isset($users['email_verified_at'])) $db->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL');
        $users=$columns('users'); $verified=$users['email_verified_at']??[];
        if (($verified['DATA_TYPE']??'')!=='datetime' || ($verified['IS_NULLABLE']??'')!=='YES'
            || ($verified['COLUMN_DEFAULT']??null)!==null || ($verified['EXTRA']??'')!=='') throw new RuntimeException('Ownership column mismatch.');
        $db->exec("CREATE TABLE IF NOT EXISTS email_verification_tokens (
            token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            user_id {$type} NOT NULL,
            email_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            expires_at BIGINT UNSIGNED NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY(token_hash), KEY idx_email_verification_user(user_id),
            KEY idx_email_verification_expiry(expires_at),
            CONSTRAINT fk_email_verification_user FOREIGN KEY(user_id) REFERENCES users(user_id)
        ) ENGINE=InnoDB");
        $tokens=$columns('email_verification_tokens'); $engine('email_verification_tokens');
        foreach(['token_hash','email_hash'] as $name) {
            $c=$tokens[$name]??[];
            if (($c['DATA_TYPE']??'')!=='char' || (int)($c['CHARACTER_MAXIMUM_LENGTH']??0)!==64
                || ($c['COLLATION_NAME']??'')!=='ascii_bin' || ($c['IS_NULLABLE']??'')!=='NO') throw new RuntimeException('Verification hash column mismatch.');
        }
        if (strtolower((string)($tokens['user_id']['COLUMN_TYPE']??''))!==$type || ($tokens['user_id']['IS_NULLABLE']??'')!=='NO'
            || !preg_match('/^bigint(\(\d+\))? unsigned$/D',strtolower((string)($tokens['expires_at']['COLUMN_TYPE']??'')))
            || ($tokens['expires_at']['IS_NULLABLE']??'')!=='NO' || ($tokens['used_at']['DATA_TYPE']??'')!=='datetime'
            || ($tokens['used_at']['IS_NULLABLE']??'')!=='YES' || ($tokens['used_at']['COLUMN_DEFAULT']??null)!==null) throw new RuntimeException('Verification token column mismatch.');
        $q=$db->prepare('SELECT INDEX_NAME,COLUMN_NAME,SUB_PART,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
        $q->execute([$schema,'email_verification_tokens']); $indexes=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $indexes[$r['INDEX_NAME']][]=$r['SUB_PART']===null ? $r['COLUMN_NAME'] : 'INVALID_PREFIX';
        if (($indexes['PRIMARY']??[])!==['token_hash'] || !in_array(['user_id'],$indexes,true) || !in_array(['expires_at'],$indexes,true)) throw new RuntimeException('Verification token keys mismatch.');
        $q=$db->prepare('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION');
        $q->execute([$schema,'email_verification_tokens']); $foreign=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $foreign[$r['CONSTRAINT_NAME']][]=[$r['COLUMN_NAME'],$r['REFERENCED_TABLE_SCHEMA'],$r['REFERENCED_TABLE_NAME'],$r['REFERENCED_COLUMN_NAME']];
        if (!in_array([['user_id',$schema,'users','user_id']],$foreign,true)) throw new RuntimeException('Verification token foreign key mismatch.');
    }

    private static function sqlite(PDO $db): void
    {
        $columns=static function(string $table) use($db): array {
            $out=[]; foreach($db->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['name']]=$r;
            return $out;
        };
        $users=$columns('users');
        if (!isset($users['user_id'],$users['email'])) throw new RuntimeException('Existing users required.');
        if (!isset($users['email_verified_at'])) $db->exec('ALTER TABLE users ADD COLUMN email_verified_at TEXT DEFAULT NULL');
        $c=$columns('users')['email_verified_at'];
        if (!in_array(strtoupper($c['type']),['TEXT','DATETIME'],true) || (int)$c['notnull']!==0 || !in_array($c['dflt_value'],[null,'NULL'],true)) throw new RuntimeException('Ownership column mismatch.');
        $db->exec('CREATE TABLE IF NOT EXISTS email_verification_tokens(token_hash TEXT NOT NULL PRIMARY KEY,user_id INTEGER NOT NULL REFERENCES users(user_id),email_hash TEXT NOT NULL,expires_at INTEGER NOT NULL,used_at TEXT DEFAULT NULL)');
        $tokens=$columns('email_verification_tokens');
        foreach(['token_hash'=>'TEXT','user_id'=>'INTEGER','email_hash'=>'TEXT','expires_at'=>'INTEGER','used_at'=>'TEXT'] as $name=>$type) {
            $c=$tokens[$name]??[];
            if (strtoupper($c['type']??'')!==$type || (int)($c['notnull']??0)!==($name==='used_at'?0:1)) throw new RuntimeException('Verification token column mismatch.');
        }
        if ((int)$tokens['token_hash']['pk']!==1 || !in_array($tokens['used_at']['dflt_value'],[null,'NULL'],true)) throw new RuntimeException('Verification token key/default mismatch.');
        $fk=$db->query('PRAGMA foreign_key_list(email_verification_tokens)')->fetchAll(PDO::FETCH_ASSOC);
        if (count($fk)!==1 || $fk[0]['from']!=='user_id' || $fk[0]['table']!=='users' || $fk[0]['to']!=='user_id') throw new RuntimeException('Verification token foreign key mismatch.');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_email_verification_user ON email_verification_tokens(user_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_email_verification_expiry ON email_verification_tokens(expires_at)');
    }
}
