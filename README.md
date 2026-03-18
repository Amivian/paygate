# paygate

PayGate: Production-Grade Paystack Integration

PayGate is a robust, full-stack payment platform designed to handle online transactions securely and reliably using the Paystack API. Built with a decoupled architecture, it features a modern, responsive Next.js 16 (TypeScript) frontend boasting a sleek dark-theme glassmorphism UI, paired with a lightweight, high-performance vanilla PHP 8.2 & MySQL backend specifically structured for easy deployment on cPanel or shared hosting environments.

Unlike basic integrations, this project is engineered for production-level resilience, implementing advanced payment engineering patterns including:

Complete Lifecycle Integration: Implements Paystack's Transaction Initialize, Verify, and List APIs via a seamless inline JS popup modal, ensuring the user never leaves the application.
Idempotency & Duplicate Prevention: Utilizes unique server-generated reference keys and strict database constraints to prevent duplicate charges—even if users double-click, refresh, or experience network drops.
Automatic Retry & Backoff Strategy: A custom built cURL client intelligently detects 5xx server errors or network timeouts and retries the API calls using exponential backoff (e.g., 1s, 2s, 4s) while avoiding retries on 4xx client errors.
Webhook Reliability & Security: Features a highly secure webhook endpoint that validates payloads using HMAC SHA-512 signatures. Webhook events act as a headless fallback to confirm payments asynchronously and employ webhook_processed database flags to safely handle duplicate Paystack events.
Auditability: Every API attempt, network failure, and response is logged into a dedicated payment_logs database table, providing total transparency for debugging and customer support.
