"""UI wiring guards; real HTTP/session/PKCE checks run in hosted fixtures."""
from pathlib import Path
import unittest
ROOT = Path(__file__).resolve().parents[1]
class AppAuthNavigationTests(unittest.TestCase):
    def test_browser_start_does_not_require_an_extra_click(self):
        source = (ROOT / 'public/app-auth.php').read_text(encoding='utf-8')
        self.assertIn("document.getElementById('google-start').submit()", source,
                      'Browser start must automatically submit its CSRF-protected form')
        self.assertIn('name="csrf_token"', source)
        self.assertIn('name="intent_id"', source)
        self.assertNotIn('<h1>Sign in to the LSC Android app</h1>', source)
    def test_completed_sign_in_redirects_to_app_without_a_success_page(self):
        source = (ROOT / 'public/app-auth.php').read_text(encoding='utf-8')
        self.assertIn("header('Location: ' . $link, true, 303)", source,
                      'Completion must redirect directly to the fixed native callback')
        self.assertNotIn('<h1>Google sign-in complete</h1>', source)
        self.assertNotIn('>Return to LSC</a>', source)
if __name__ == '__main__':
    unittest.main()
