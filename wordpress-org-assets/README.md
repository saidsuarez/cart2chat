# WordPress.org Assets Checklist (Cart2Chat)

Use this folder as your working area for assets before uploading to WordPress.org SVN.

## Required filenames (exact)

- `icon-128x128.png`
- `icon-256x256.png`
- `banner-772x250.png`
- `banner-1544x500.png`

## Optional

- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`
- `screenshot-5.png`

These screenshot filenames should match the list and order in `readme.txt`.

## Upload location in WordPress.org SVN

After plugin approval, upload visual assets to the SVN `assets` directory:

- `assets/icon-128x128.png`
- `assets/icon-256x256.png`
- `assets/banner-772x250.png`
- `assets/banner-1544x500.png`
- `assets/screenshot-1.png` (optional)

## Suggested workflow

1. Design/export all images with the exact filenames above.
2. Keep optimized PNGs (small file size, no visible artifacts).
3. Commit assets to this Git repo for versioning.
4. Copy the final files to WordPress.org SVN `assets/`.
5. Commit SVN changes with a release note.

## Design notes

- Keep icon legible at small sizes (128x128).
- Avoid small text in banners.
- Use clear branding: `Cart2Chat` and `by Pinxel`.
- Maintain sufficient contrast for accessibility.
