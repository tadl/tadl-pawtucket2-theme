# tadl-pawtucket2-theme

Standalone source repository for the `tadl` Pawtucket2 theme used for TADL local history.

This repo is intended for theme work only. Pawtucket2 core and Providence core are kept in separate reference repos.

Typical local reference paths:

- `../pawtucket2`
- `../providence`

Typical deploy flow:

1. make changes in this repo
2. commit and push to GitHub
3. rsync the theme to the Pawtucket2 server theme directory
4. clear `app/tmp`
5. restart Apache

Current theme name on the server: `tadl`
