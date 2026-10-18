"""Regression for the missing required rate limiter on the group index route."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]

class GroupRouteWiring(unittest.TestCase):
    def test_both_group_routes_inject_required_rate_limiter(self):
        for route in ['groups.php', 'group.php']:
            with self.subTest(route=route):
                source = (ROOT / 'public' / route).read_text()
                self.assertIn('use LSC\\Core\\RateLimiter;', source)
                self.assertRegex(source, r'new GroupController\([\s\S]*,\s*new RateLimiter\(\$database\)\s*\)')

if __name__ == '__main__':
    unittest.main(verbosity=2)
