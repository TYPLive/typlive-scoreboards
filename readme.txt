=== TYP Live Scoreboards ===
Contributors: typlive
Tags: scoreboard, streamlabs, pool, live scores, overlay
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.5.4
License: GPLv2 or later

Streamlabs-ready live pool scoreboards with stable player URLs.

== Description ==

TYP Live Scoreboards creates transparent browser-source overlays at routes such as:

* /scoreboard-sam/
* /scoreboard-nigel/

The Spots & Stripes on Tour build includes ready-to-use links for Sam, Nigel, Michael, Joe, Ollie, Ashley, Sean and Mary. Additional players can still be selected in Settings > TYP Live Scoreboards.

The combined Spots & Stripes on Tour schedule is available at /upusa-schedule/.
The live subscribed calendar for every tracked player is available at /upusa-calendar.ics, with player and pair feeds available at /upusa-calendar-{slug}.ics.

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

= 0.5.4 =
* Removed the scoreboard drop shadow and forced the surrounding Streamlabs canvas to remain fully transparent.

= 0.5.3 =
* Aligns both player names toward the centre score block for a balanced overlay.

= 0.5.2 =
* Added a compact Race to target between the two score blocks.
* Displays both player names in capitals on the scoreboard.

= 0.5.1 =
* Removed the optional OneSignal match-notification integration and all external setup requirements.
* Removes any saved OneSignal credentials during the update.

= 0.5.0 =
* Added opt-in OneSignal web push notifications for phones and desktop browsers.
* Sends alerts for new matches, table, opponent, time and live-status changes.
* Added configurable pre-match reminders and an admin test-alert button.
* Added a root OneSignal service worker and an iPhone-compatible web app manifest.
* Keeps the OneSignal REST API key private on the WordPress server.

= 0.4.4 =
* Moved the scoreboard quick links below the schedule and results.

= 0.4.3 =
* Restored the per-match Add to calendar download on the next-match card and schedule rows.
* Separated one-time calendar events from the live subscribed calendar.
* Renamed the live feed control to Subscribe for automatic updates.

= 0.4.2 =
* Changed the background DigitalPool schedule monitor from five minutes to one minute.
* Automatically migrates existing sites to the new one-minute WordPress cron recurrence.
* Saves each successful schedule check for the subscribed calendar feeds.
* Calendar requests use the saved one-minute schedule and fall back to a direct DigitalPool check when it is older than 90 seconds.

= 0.4.1 =
* Replaced one-time calendar downloads with live subscribed calendar feeds.
* Added stable event IDs and change sequences so table, opponent and time updates replace the existing calendar event.
* Added all-player and player/pair-specific calendar feeds with 30-second refresh hints.
* Changed the public schedule to check DigitalPool every 30 seconds while the page is open.
* Added the live calendar feed URL to WordPress admin.

= 0.4.0 =
* Added a live countdown for the next match, following the selected player filter.
* Added share/copy controls for every scoreboard URL.
* Added phone-friendly calendar downloads for scheduled matches.
* Shows event-local Eastern Time and UK time together on each dated match.
* Shows the last successful DigitalPool update on the public schedule and in WordPress admin.
* Added five-minute schedule monitoring with admin alerts for opponent, table and match-time changes.
* Added a manual DigitalPool check and alert history controls in WordPress admin.

= 0.3.7 =
* Added quick-link buttons for every tracked player's live scoreboard.
* Kept the scoreboard links alphabetical and mobile-friendly without horizontal scrolling.

= 0.3.6 =
* Added combined pair-team options to the schedule filters.
* Pair matches show both full player names on schedule rows and live scoreboards.
* De-duplicated pair fixtures that belong to two tracked players.

= 0.3.5 =
* Updated the Spots & Stripes on Tour event introduction and venue wording.

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

