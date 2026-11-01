"""Focused navigation regression guards. No network, credentials or database."""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]

class NavigationTests(unittest.TestCase):
    def test_profile_has_no_second_google_control(self):
        profile = (ROOT / 'views/users/profile.php').read_text(encoding='utf-8')
        self.assertNotIn('oauth.php?provider=google', profile,
                         'Profile must not contain a second Google sign-in/connect form')
        self.assertNotIn('Connect Google', profile)
        self.assertNotIn('Continue with Google', profile)
        self.assertIn('name="update_profile"', profile)
        self.assertIn('name="update_notifications"', profile)

    def test_help_is_reachable_before_and_after_sign_in(self):
        self.assertTrue((ROOT / 'public/help.php').is_file(), 'Public Help route is missing')
        for route in ['public/index.php', 'views/layouts/auth.php', 'views/layouts/app.php']:
            self.assertIn('help.php', (ROOT / route).read_text(encoding='utf-8'),
                          'Help link missing from ' + route)

if __name__ == '__main__':
    unittest.main()
