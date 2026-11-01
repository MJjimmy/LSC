"""Offline checks for the hardened Railway Docker runtime."""
from pathlib import Path
import re
import unittest
ROOT=Path(__file__).resolve().parents[1]
class RuntimeContract(unittest.TestCase):
    def test_runtime_uses_pinned_non_root_vendor_images(self):
        path=ROOT/'Dockerfile.railway'
        self.assertTrue(path.exists(),'A patched production runtime is required')
        s=path.read_text()
        bases=re.findall(r'^FROM\s+(\S+)',s,re.M)
        self.assertEqual(len(bases),3)
        for image in bases:self.assertRegex(image,r'^cgr\.dev/chainguard/(php|nginx|wolfi-base)@sha256:[a-f0-9]{64}$')
        self.assertIn('USER 65532:65532',s)
        self.assertNotIn('COPY . ',s)
        self.assertNotIn('apt-get',s)
        for filename in ['Dockerfile.railway', 'Dockerfile.database']:
            self.assertNotIn('RUN --network=', (ROOT/filename).read_text(),
                             'Railway does not support per-instruction network flags')
    def test_nginx_only_serves_public_and_uses_fpm(self):
        path=ROOT/'runtime/nginx.conf.template'
        self.assertTrue(path.exists())
        s=path.read_text()
        for value in ['root /app/public;','listen __PORT__;','fastcgi_pass unix:/tmp/lsc/php-fpm.sock;','location = /healthz','return 404;']:
            self.assertIn(value,s)
        self.assertNotIn('php -S',s)
    def test_supervision_uses_vendor_init_and_private_socket(self):
        path=ROOT/'runtime/start'
        self.assertTrue(path.exists())
        self.assertIn('/bin/s6-svscan /tmp/lsc/sv',path.read_text())
        pool=(ROOT/'runtime/php-fpm.conf').read_text()
        self.assertIn('listen = /tmp/lsc/php-fpm.sock',pool)
        self.assertIn('clear_env = no',pool)
        self.assertIn('ping.path = /healthz',pool)
    def test_setup_validates_port_and_keeps_secrets_out_of_config(self):
        path=ROOT/'runtime/prepare.php'
        self.assertTrue(path.exists())
        s=path.read_text()
        self.assertIn('FILTER_VALIDATE_INT',s)
        self.assertIn('65535',s)
        self.assertNotIn('MYSQLPASSWORD',s)
        self.assertNotIn('shell_exec',s)
if __name__=='__main__':unittest.main(verbosity=2)
