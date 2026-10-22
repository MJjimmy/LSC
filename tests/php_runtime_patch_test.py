"""Runtime-only release guard. Never executes the pre-update image."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
PIN = 'cgr.dev/chainguard/php@sha256:8cdaa7a48161331e1de2df8cce48c02d2b67890aaf629823172790e05c7f4738'

class PhpRuntimePatch(unittest.TestCase):
    def test_php_image_is_exact_signature_verified_8512_fpm_release(self):
        source = (ROOT / 'Dockerfile.railway').read_text()
        images = re.findall(r'^FROM\s+(\S+)', source, re.M)
        self.assertEqual(images[-1], PIN, 'Replace the pre-security-release PHP image before executing it')

    def test_build_fails_closed_unless_php_is_exactly_8512(self):
        source = (ROOT / 'Dockerfile.railway').read_text()
        self.assertIn("PHP_VERSION !== '8.5.12'", source)
        self.assertIn('Verified PHP runtime: ', source)

if __name__ == '__main__':
    unittest.main(verbosity=2)
