# Webhook signing contract

Generic incoming and all outgoing Apiqendra signatures use:

`v1=HMAC_SHA256(secret, decimalTimestamp + "." + exactRawBody)`

Generic ingress requires a string id in the signed JSON body that exactly matches X-Event-ID. The ID must be chosen before signing. This prevents replaying captured bytes under a different unsigned header.

Headers: X-Apiqendra-Timestamp (Unix seconds), X-Apiqendra-Signature, X-Event-ID. Outgoing requests also include X-Delivery-ID and Idempotency-Key. Verify the timestamp within 300 seconds and use a timing-safe comparison.

The Node helper is packages/contracts/signing.mjs. Its fixed vector is independently confirmed with Python hmac:

| Input | Value |
| --- | --- |
| Secret | synthetic-test-key |
| Timestamp | 1700000000 |
| Raw body | {"hello":"world"} |
| Signature | v1=a86c12f10bed5d09cf42d3455218be90f5d859dacbbbc946aa2c29f79631670b |

GitHub uses sha256=HMAC_SHA256(secret, exactRawBody) without the timestamp prefix. It does not provide signed timestamp freshness. Delivery-ID and signed-content deduplication are both enforced. Identical GitHub body bytes represent one logical event per endpoint, even if the delivery header changes. Supported GitHub events are issues and ping. This does not create an exactly-once delivery guarantee.

Do not parse and reserialize JSON before verifying a signature. Whitespace and key order change the bytes. Reusing a source ID with changed bytes returns a conflict.
