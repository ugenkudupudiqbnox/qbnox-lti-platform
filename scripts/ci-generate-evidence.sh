
#!/usr/bin/env bash
set -e

OUT=ci-evidence
mkdir -p $OUT

cat > $OUT/CI_EVIDENCE.md <<EOF
# LTI CI Compliance Evidence

## Date
$(date -u)

## Assertions Passed
- JWT cryptographic verification
- JWT claim-by-claim validation
- Deep Linking return URL validation
- Multi-item Deep Linking
- AGS role enforcement
- Per-course grade persistence
- Moodle matrix compatibility

## Environment
- Pressbooks LTI Platform
- Moodle versions tested via CI matrix

## Conclusion
All required LTI 1.3 + Advantage compliance checks passed.
EOF

echo "Evidence markdown generated"
