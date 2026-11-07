# Changelog

## Unreleased private candidate — 2026-11-06

Adds an opt-in private PDF DocuSign transport for exact application-owned requests. It supports JWT with validated RSA PKCS#1 or PKCS#8 keys, separate demo and production OAuth endpoints, production account-bound UserInfo base URI verification, one transaction ID per send, ambiguous-response lookup, Connect HMAC verification, and bounded authenticated completed PDF and certificate retrieval. The application owns tenant access, consent records, business state, and private retention. This work is untagged, unpublished and has made no provider request; the released v2.0.0 adapters remain unchanged.

## 2.0.0 — 2026-09-12

Laravel 12/13; Dompdf 3.1.6+; testing dependencies removed from production. Fixed the HelloSign class/autoload collision, supplied the default template, disabled remote PDF assets and local-file access by default, used UUID storage names and checked write failures. Built-in external signature adapters now explicitly reject unsupported operations. Local demonstrations and callbacks require opt-in; callbacks require expiring signed URLs and a pending local document. Failed provider requests no longer mark documents as signing.
