# Onward Web Migration (Unlimited Import Size)

Welcome to Onward Web Migration! In this version, we've no size restriction on importing or restoring backups, allowing you to seamlessly transfer websites of any size.

## Features:

- **Unlimited Import Size:** Onward Web Migration enables you to import or restore backups without any size limitations, providing you with the flexibility to migrate websites of any scale.
- **Seamless Migration:** Export your WordPress site, including the database, media files, plugins, and themes, and import it into any location within WordPress with ease.
- **Drag-and-Drop Functionality:** Utilize WordPress's intuitive drag-and-drop interface to effortlessly upload and import your site backup, simplifying the migration process.
- **Enhanced Flexibility:** No size restrictions, you have the freedom to transfer large-scale websites, ensuring that your migration process is smooth and hassle-free.

## Getting Started:

To begin using Onward Web Migration, simply download the plugin. Once installed, you'll have access to the unrestricted import functionality, allowing you to import backups of any size without encountering limitations.

## Source Code & Build Instructions

Onward Web Migration is free and open-source software under GPLv2 or later.

The source repository is maintained at:
https://github.com/cj-careconnect/OWM

### JavaScript Assets:
- `unified-workspace.js`: Modern unified migration UI controller (unminified, human-readable).
- `browser-zip.js`: Streaming ZIP64 and Web Worker compression engine (unminified, human-readable).
- `util.min.js`, `import.min.js`, `export.min.js`, `backups.min.js`: Modular JavaScript components bundled via Webpack. Build steps:
  1. `git clone https://github.com/cj-careconnect/OWM.git`
  2. `npm install`
  3. `npm run build`

## Third-Party Libraries

- **Math_BigInteger** (`BigInteger.php`): Pure-PHP arbitrary precision integer arithmetic library from phpseclib by TerraFrost. License: MIT. (https://github.com/phpseclib/phpseclib)
- **Bandar** (`Bandar.php`): Minimal template renderer. License: MIT. (https://github.com/bandar/bandar)
- **Browser ZIP Engine** (`browser-zip.js`): Browser-based client-side streaming ZIP archive handler. License: MIT / GPLv2+.

Happy migrating!
