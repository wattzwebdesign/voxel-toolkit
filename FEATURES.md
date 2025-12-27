# Feature Tracking

Track feature development status for Voxel Toolkit releases.

## In Development

Features currently being worked on:

- [ ] `feature/analytics-integration` - Analytics dashboard integration
- [ ] `feature/booking-reminders` - Booking reminders as native Voxel app events
- [ ] `feature/dynamic-membership-links` - Membership checkout links with AJAX support
- [ ] `feature/settings-overhaul` - Settings page overhaul

## Ready for Release

Features completed and ready to bundle into next release:

_(none currently)_

## Released in 1.5.8 (2025-12-12)

- Suggest Edits Widget modal z-index fix
- Membership Plan Filter profiles fix
- SMS Notifications child theme fix
- Messenger Widget toggle/tooltip fixes

## Released in 1.5.7 (2025-12-07)

- Calendar Week Start function
- Auto Reply Dynamic Tag
- Share Count function with dynamic tags
- Docs Menu Link
- Messenger Settings AJAX save fix
- Messenger Widget reply-as placeholder fix

---

## Quick Reference

```bash
# Start new feature
git checkout development && git checkout -b feature/my-feature

# Merge feature to development for testing
git checkout development && git merge feature/my-feature

# Create release
git checkout main && git merge development
git tag -a v1.6.0 -m "Release 1.6.0"
git push origin main --tags
```
