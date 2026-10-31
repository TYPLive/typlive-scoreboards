=== TYP Live Scoreboards ===
Contributors: typlive
Tags: scoreboard, streamlabs, pool, live scores, overlay
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.3.4
License: GPLv2 or later

Streamlabs-ready live pool scoreboards with stable player URLs.

== Description ==

TYP Live Scoreboards creates transparent browser-source overlays at routes such as:

* /scoreboard-sam/
* /scoreboard-nigel/

The Spots & Stripes on Tour build includes ready-to-use links for Sam, Nigel, Michael, Joe, Ollie, Ashley, Sean and Mary. Additional players can still be selected in Settings > TYP Live Scoreboards.

The combined Spots & Stripes on Tour schedule is available at /upusa-schedule/.

Use Settings > TYP Live Scoreboards to configure player mappings, demo scores, styling, the live-scoring source and refresh rate.

The plugin reads the Ultimate Pool USA tournament catalogue and live scores from All Scores Live. It also reads the 2026 League Grand Finals schedule and the National Mixed Pairs and National Pairs team entries from DigitalPool for opponent, round and table allocation data.

== Installation ==

1. Upload the plugin ZIP in WordPress under Plugins > Add New > Upload Plugin.
2. Activate TYP Live Scoreboards.
3. Open Settings > TYP Live Scoreboards.
4. Choose a tournament from the live event list.
5. Select players from the alphabetical checklist and save.
6. Copy each generated scoreboard URL into Streamlabs as a browser source.
7. Share /upusa-schedule/ as the combined public schedule page.

If a route returns a WordPress 404, open Settings > Permalinks and click Save Changes once.

== Streamlabs notes ==

Scoreboard routes such as /scoreboard-sam/ have a transparent background. A 1280 x 300 browser-source canvas is a good starting point for a landscape lower-third overlay. The design automatically scales down for narrower mobile canvases.

== Changelog ==

= 0.3.4 =
* Hide the Live now section unless the current player filter has a live match.

= 0.3.3 =
* Sorted the desktop player filter and mobile player dropdown alphabetically by display name.

= 0.3.2 =
* Renamed the public event experience to Spots & Stripes on Tour.
* Added DigitalPool team-roster matching for the National Mixed Pairs and National Pairs.
* Includes Mary Talbot and Joe Prince as a mixed pair, plus Joe Prince and Michael Hope as a national pair.

= 0.3.1 =
* Added separate Live now, Upcoming matches and Results sections to the combined schedule.
* Live matches show current scores and completed matches show final scores with win, loss or draw indication.
* Replaced the scrolling player buttons with a player dropdown on mobile phones.

= 0.3.0 =
* Added a Check for updates now button to the plugin settings page.
* Manual checks bypass the release cache and refresh WordPress update data immediately.
* Shows a clear current, update available or connection error result after checking.

= 0.2.9 =
* Includes every published non-completed match even when its date and time are still TBC.
* Sorts date-and-time TBC matches after scheduled matches.
* Removed the repeated Tracked player subtitle beneath every player name.

= 0.2.8 =
* Added secure update checks through the public TYP Live GitHub Releases feed.
* Enables automatic WordPress updates for this plugin by default.
* Added release packaging automation for future versions.

= 0.2.7 =
* Added one combined public schedule at /upusa-schedule/.
* Added chronological matches and player filter buttons for all eight tracked players.
* Restored every /scoreboard-player/ route to a scoreboard-only Streamlabs page.

= 0.2.6 =
* Player pages now list up to eight upcoming or current DigitalPool matches.
* Added a scoreboard-only Streamlabs view using ?overlay=1.
* Schedule rows refresh with the live score feed so table allocations update automatically.

= 0.2.5 =
* Added eight built-in tracked players supplied for the Grand Finals.
* Generates stable first-name scoreboard URLs without requiring an All Scores Live player ID.
* Built-in players use DigitalPool schedules and scores immediately, with table allocations picked up automatically.

= 0.2.4 =
* Added the DigitalPool 2026 League Grand Finals schedule feed.
* Uses DigitalPool for current/upcoming opponent, round and table allocation.
* Retains All Scores Live as the preferred score source with DigitalPool live scores as a fallback.

= 0.2.3 =
* Reduced the public overlay to round name, player names and scores only.
* Made both score blocks use identical foreground and background colours.

= 0.2.2 =
* Removed all logos and branding from the public scoreboard overlay.
* Replaced the graphic treatment with a minimal black strip and restrained red accent.

= 0.2.1 =
* Reworked the overlay as a thin, single-row horizontal score strip.
* Added responsive detail hiding so narrow layouts remain horizontal.

= 0.2.0 =
* Initial design preview.
* Stable player routes and shortcode.
* Tournament picker and alphabetical player checklist powered by the All Scores Live API.
* Auto-refreshing live-match feed with a standby state between matches.
* Player-first URL generation such as /scoreboard-sam/.

