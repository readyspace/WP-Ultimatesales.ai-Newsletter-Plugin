# WordPress.org directory assets

These are directory presentation assets, separate from the runtime plugin. The original `directory-assets/icon.svg` uses a neutral envelope and delivery arrow; it is not a recreation of an official corporate logo. ReadySpace has authorized GPLv2-or-later licensing for the original directory artwork and confirmed the necessary rights. See LICENSE and COPYRIGHT.txt.

After plugin approval, place final artwork in the top-level SVN `assets/` directory beside `trunk/` and `tags/`. Do not put directory artwork in `trunk/assets/` or a release-tag assets folder. It need not be included in the installable runtime ZIP.

## Icon and banner files

The candidate includes the following original files at the exact dimensions in their filenames:

| File | Dimensions | Notes |
| --- | --- | --- |
| `icon.svg` | Scalable; current viewBox 256 by 256 | Original vector design. Include a PNG fallback when using SVG. |
| `icon-128x128.png` | 128 by 128 | Normal icon/fallback. |
| `icon-256x256.png` | 256 by 256 | High-resolution icon. |
| `banner-772x250.png` | 772 by 250 | Normal directory banner. |
| `banner-1544x500.png` | 1544 by 500 | Optional high-resolution banner, accompanied by the normal banner. |

Icons must be below 1 MB and banners below 4 MB. Use lowercase filenames. An icon is optional; WordPress generates one if none is provided. Do not include external images or fonts with unverified redistribution rights.

## Screenshots

Capture actual plugin screens from an isolated staging site using fictional configuration and controlled recipients. Never publish tokens, customer data, real subscriber lists, private account identifiers or screenshots of a production customer account. Do not fabricate screenshots or label visual mockups as working screens.

Suggested captures after the relevant checks pass:

1. `screenshot-1.png`: The Tools -> ReadySpace Newsletter screen with sending Off and no saved credential displayed.
2. `screenshot-2.png`: The post newsletter exclusion control and the Excerpt field, using a fictional article.
3. `screenshot-3.png`: A controlled draft-only job, with fictional/non-sensitive identifiers and actual tested status.

Only add a `== Screenshots ==` section to readme.txt when the corresponding files exist. Every numbered caption must have its matching `screenshot-N.png` or `.jpg`. Screenshots must be local, use lowercase filenames and be below 10 MB each. Crop for clarity without changing the represented product behaviour.

## Release review

Before public submission or SVN upload, confirm the final name/slug, licence rights for all artwork, exact pixel dimensions and file sizes. Inspect each icon/banner at its intended display size and check screenshots against the actual candidate. The repository and readme remain honest about the alpha until production acceptance is complete.

Official specifications: [How Your Plugin Assets Work](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/).
