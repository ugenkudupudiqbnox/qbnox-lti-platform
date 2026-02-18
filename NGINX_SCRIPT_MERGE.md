# Nginx Setup Script Merge

**Date**: 2026-02-18  
**Status**: ✅ Complete

## Summary

Merged `scripts/setup-local-nginx.sh` and `scripts/setup-nginx-ssl.sh` into a single unified script: `scripts/setup-nginx.sh`

## What Changed

### New Unified Script: `scripts/setup-nginx.sh`

**Features:**
- ✅ Automatic environment detection (local vs production)
- ✅ Reads `PROTOCOL` setting from `.env` file
- ✅ Supports both HTTP and HTTPS configurations
- ✅ Graceful SSL failure handling (falls back to HTTP)
- ✅ Idempotent (checks if domains already configured)
- ✅ Removes default nginx site automatically
- ✅ Updates Docker application configs when needed
- ✅ Comprehensive help documentation (`--help`)

### Backward Compatibility

The old scripts are now thin wrappers that redirect to the new unified script:
- `scripts/setup-local-nginx.sh` → redirects to `setup-nginx.sh`
- `scripts/setup-nginx-ssl.sh` → redirects to `setup-nginx.sh`

Existing workflows continue to work without modification.

## Usage

### Automatic (Recommended)
```bash
sudo bash scripts/setup-nginx.sh
```
- Detects local development (`.local` domains) → HTTP only
- Detects production → Attempts HTTPS, falls back to HTTP if needed
- Uses `PROTOCOL` setting from `.env` file

### Force HTTP-only
```bash
sudo bash scripts/setup-nginx.sh --skip-ssl
```

### Show Help
```bash
bash scripts/setup-nginx.sh --help
```

## Behavior

### Local Development (`.local` domains)
- Skips SSL/certbot installation
- Creates HTTP-only nginx configs
- No DNS verification needed
- Ideal for Docker-based local development

### Production (non-`.local` domains)
1. **If `PROTOCOL=http` in .env**: HTTP-only setup
2. **If `PROTOCOL=https` in .env**:
   - Attempts SSL certificate acquisition via certbot
   - Verifies DNS records before certbot
   - Falls back to HTTP if SSL fails
   - Updates application configs to use HTTPS

## Key Features Merged

**From `setup-local-nginx.sh`:**
- `is_domain_configured()` function for idempotency
- Removal of default nginx site
- Simpler flow for local development

**From `setup-nginx-ssl.sh`:**
- SSL/HTTPS support with certbot
- DNS verification before SSL
- Graceful failure handling
- Application configuration updates
- Comprehensive help documentation

## Files Updated

**Created:**
- `scripts/setup-nginx.sh` (unified script)

**Modified (now wrappers):**
- `scripts/setup-local-nginx.sh`
- `scripts/setup-nginx-ssl.sh`

**Updated references:**
- `Makefile` (line 7): `setup-nginx` target
- `scripts/lab-up.sh` (line 157): nginx proxy setup

## Benefits

1. **Single source of truth**: One script to maintain
2. **Consistent behavior**: Same logic for all environments
3. **Better error handling**: Graceful fallbacks instead of failures
4. **Automatic detection**: No need to remember which script to use
5. **Backward compatible**: Old scripts still work via redirection
6. **Idempotent**: Safe to run multiple times

## Testing

Validate the script:
```bash
bash -n scripts/setup-nginx.sh
```

Test local development setup:
```bash
# With .local domains in .env
sudo bash scripts/setup-nginx.sh
```

Test production setup:
```bash
# With production domains and PROTOCOL=https in .env
sudo bash scripts/setup-nginx.sh
```

Test HTTP-only override:
```bash
sudo bash scripts/setup-nginx.sh --skip-ssl
```

## Migration Notes

**No action required!** The old script names continue to work via wrapper redirection.

**Optional cleanup** (after testing):
- Consider updating any documentation to reference `setup-nginx.sh` directly
- Consider removing wrapper scripts after transition period

---

**Last Updated**: 2026-02-18  
**Author**: Claude Code
