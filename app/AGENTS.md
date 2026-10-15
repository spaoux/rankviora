# RankViora Laravel application

Follow ../AGENTS.md and the root README.md. Runtime and dependency tools run inside Docker; host PHP installation is unnecessary.

Installed stack: Laravel 13, PHP 8.5, Livewire 4, Tailwind CSS 4, PHPUnit 12. Laravel Boost is installed as a development dependency.

Authorize workspace membership on every request and Livewire action. Scope all tenant reads and mutations through workspace relationships. Only owners may write; members may read.

Use explicit return types, validate inputs, and format PHP with vendor/bin/pint. Tests must use the guarded SQLite memory configuration in tests/TestCase.php and must never target the application PostgreSQL database.

No paid API calls, production deployment, VPS access, commit, or push without the user's requested authorization.
