# shell

## pink.php (authorized testing only)

Single-file **read-only** PHP probe: JSON with PHP version, SAPI, loaded extensions, and a few `php.ini` limits. **No** command execution or file browsing.

1. Edit `pink.php` and set `PINK_PROBE_KEY` to a long random secret.
2. Upload to a server **you own** or have **written permission** to test.
3. Request `https://your-host/pink.php?key=YOUR_SECRET`
4. Delete the file after testing.

For CLI helpers, see `bin/siteshell` if present on your branch.

Do not use this project to access systems without authorization.
