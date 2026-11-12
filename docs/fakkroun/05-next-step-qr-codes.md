# Next step 05: QR-code invitations

> **Priority:** high (spec deliverable 13) · **Effort:** small · **Spec:** §12.2
> **Depends on:** 03

## Status (2026-11-12): done, without a new dependency

- The waiting room shows the invitation link as an inline SVG QR code (`RoomService::invitationQr()`).
  The encoder is `App\Support\QrCode\QrCode` (byte mode, level M, versions 1–9), written in the project
  so no package had to be added; its output was compared module by module with two other encoders.
- `/join/{code}` is now a page: visitors pick any nickname (guest player) or sign in and come back;
  signed-in players see the account they play with. Both enter the room with "Join the game".
- Not done from the design below: the separate `qr.svg` route, the full-screen toggle and the A4 print layout.

The text below is the original plan, kept as written.

## Current state

`/join/{code}` already implements the whole scan flow: open → authenticate (login redirect and return) → validate room → joinable → capacity → join. The lobby shows the link with copy, Web Share and WhatsApp. Only the **image** is missing.

## Design

- Server-side **SVG** generation with `bacon/bacon-qr-code` (pure PHP, no Imagick/GD). The image is never sent to a third-party QR service, so room links don't leak.
- `GET /rooms/{ulid}/qr.svg`, for room members only. Cached with `Cache-Control: private, max-age=3600`. Encodes `Room::invitationUrl()`.
- Lobby: the QR shown large on desktop, and a "Show QR" toggle on mobile so the host can present it full-screen to a class.
- Optional `?print=1` layout: room name, code and QR on one A4 page (spatie/laravel-pdf is already installed).

## Tasks

1. `composer require bacon/bacon-qr-code`. Add a `RoomInvitationService::qrSvg(Room)` service.
2. Controller + route + policy (membership).
3. Lobby UI (light/dark aware: draw on a white tile for contrast).
4. Tests: SVG content type, members only, decoding the QR yields the invitation URL (assert the encoded string via the library's matrix, or snapshot).

## Done when

- Scanning the lobby QR with a phone camera opens Fakkroun, asks to log in if needed, and lands in the lobby.
