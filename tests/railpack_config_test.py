"""Offline regression checks for the Railway/Railpack deployment contract."""
import configparser
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class RailpackConfigTests(unittest.TestCase):
    def test_php_is_detectable_with_required_extensions(self):
        path = ROOT / 'composer.json'
        self.assertTrue(path.is_file(), 'Root composer.json must make PHP detectable')
        config = json.loads(path.read_text())
        self.assertEqual(config['require']['php'], '^8.4')
        for extension in ('ext-pdo_mysql', 'ext-mbstring', 'ext-curl', 'ext-fileinfo'):
            self.assertIn(extension, config['require'])
        self.assertNotIn('scripts', config, 'No Composer execution hooks needed')

    def test_railpack_selects_php_and_production_defaults(self):
        path = ROOT / 'railpack.json'
        self.assertTrue(path.is_file(), 'Railpack configuration is required')
        config = json.loads(path.read_text())
        self.assertEqual(config['provider'], 'php')
        self.assertEqual(config['deploy']['variables']['APP_ENV'], 'production')
        self.assertEqual(config['deploy']['variables']['APP_DEBUG'], '0')
        self.assertEqual(config['deploy']['startCommand'], '/start-container.sh')
        self.assertIn('**/.env', config['exclude'])
        self.assertIn('**/.env.*', config['exclude'])
        self.assertIn('storage/uploads/**', config['exclude'])

    def test_only_public_is_served_and_health_does_not_redirect(self):
        path = ROOT / 'Caddyfile'
        self.assertTrue(path.is_file(), 'Production Caddy configuration is required')
        config = path.read_text()
        self.assertIn('root * /app/public', config)
        self.assertIn(':{$PORT:8080}', config)
        self.assertIn('respond /healthz "ok" 200', config)
        self.assertIn('php_server', config)
        self.assertIn('rewrite @missing /404.php', config)
        self.assertIn('respond @private 404', config)
        self.assertLess(config.index('route {'), config.index('respond /healthz'),
                        'Health must execute inside the ordered route')
        self.assertLess(config.index('respond /healthz'), config.index('rewrite @missing'),
                        'Health must bypass the application missing-file rewrite')

    def test_upload_and_error_limits_are_explicit(self):
        path = ROOT / 'php.ini'
        self.assertTrue(path.is_file(), 'PHP production settings are required')
        config = configparser.ConfigParser()
        config.read_string('[PHP]\n' + path.read_text())
        self.assertEqual(config['PHP']['upload_max_filesize'], '100M')
        self.assertEqual(config['PHP']['post_max_size'], '110M')
        self.assertEqual(config['PHP']['memory_limit'], '256M')
        self.assertEqual(config['PHP']['display_errors'], 'Off')
        self.assertEqual(config['PHP']['expose_php'], 'Off')

    def test_legacy_start_commands_cannot_override_railpack(self):
        for name in ('railway.json', 'nixpacks.toml', 'Procfile'):
            self.assertFalse((ROOT / name).exists(), f'Remove obsolete startup override: {name}')


if __name__ == '__main__':
    unittest.main(verbosity=2)
