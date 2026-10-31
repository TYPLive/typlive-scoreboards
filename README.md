# TYP Live Scoreboards

WordPress plugin for Spots & Stripes on Tour live scoreboards and the combined tracked-player schedule at `/upusa-schedule/`.

## Automatic updates

The plugin checks this repository's latest public GitHub Release. Every tagged release contains an installable asset named `typlive-scoreboards.zip`; WordPress detects newer semantic versions and installs them automatically.

## Publishing a release

1. Update the plugin version in `typlive-scoreboards.php` and the stable tag in `readme.txt`.
2. Commit the changes.
3. Push a matching semantic-version tag, such as `v0.3.0`.

The release workflow validates the version, builds the correctly nested WordPress ZIP and publishes it to GitHub Releases.
