# Hochzeit Musikwünsche Plugin

Sammelt Musikwünsche der Gäste über die Website und synchronisiert diese automatisch in Spotify- und Apple-Music-Playlists.

## Features

- **Frontend-Formular** mit Live-Suche bei Spotify (Tippen → Songs vorgeschlagen)
- **Manuelle Eingabe** mit Titel, Interpret oder Spotify-/Apple-Music-Link
- **Automatische Dublettenerkennung**: gleicher Titel + Interpret → Zähler steigt, alle Wünscher werden vermerkt
- **Brautpaar-Markierung** ★ für eigene Eingaben im Backend
- **Auto-Sync** in beide Playlists nach jedem Wunsch
- **Backend-Liste** mit Direkt-Links zu Spotify/Apple Music und Re-Sync-Buttons
- **Excel-Export** der gesamten Wunschliste
- **Anpassbare Texte und Farben**
- **GitHub-Auto-Update**

## Installation

1. ZIP unter Plugins → Plugin hochladen einspielen
2. Aktivieren – Datenbank wird automatisch angelegt
3. Unter **🎵 Musikwünsche → ⚙ Einstellungen** die API-Keys eintragen

## Shortcode

`[musikwuensche]` auf einer Seite einfügen (Avada: Shortcode-Element verwenden).

## Spotify einrichten

1. https://developer.spotify.com/dashboard öffnen
2. „Create app" → Name beliebig, Redirect URI aus den Plugin-Einstellungen kopieren
3. Client ID + Client Secret in Plugin-Einstellungen eintragen → Speichern
4. „Mit Spotify verbinden" klicken → autorisieren
5. Playlist-ID eintragen (aus der Playlist-URL)

## Apple Music einrichten

Erfordert kostenpflichtige Apple-Developer-Mitgliedschaft (99 €/Jahr):

1. https://developer.apple.com/account → Keys → MusicKit Key erstellen
2. Team ID, Key ID und .p8-Datei-Inhalt in Plugin-Einstellungen eintragen
3. Music User Token einmal über MusicKit JS holen und einfügen
4. Playlist-ID eintragen
