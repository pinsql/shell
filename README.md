# shell

## `pink.php` — read-only health probe

Small JSON endpoint for **servers you own** or are **explicitly allowed** to test. No command execution or file access beyond reporting PHP metadata.

1. Set a secret: edit `PINK_PROBE_KEY` in `pink.php`, **or** set environment variable `PINK_PROBE_KEY` (env overrides the constant when non-empty).
2. Call with the key: `?key=SECRET`, **or** header `Authorization: Bearer SECRET`, **or** `X-Pink-Key: SECRET`.
3. Optional: `lite=1` for a faster, smaller response (extension count only). `pretty=1` for formatted JSON (default is compact).
4. Remove `pink.php` when finished.

Response includes `gen_ms` (time to build the JSON on the server).
