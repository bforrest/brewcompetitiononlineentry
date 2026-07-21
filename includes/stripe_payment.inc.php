<?php
/**
 * To integrate Stripe as a payment option for users,
 * admins must have access to an active Stripe account
 * and they must create a "product" with the price equal
 * to one entry.
 * 
 * In Site Preferences, provide:
 *  - API Keys
 *  - Entry fee "product"
 *
 * These values are account-specific credentials and must never be
 * hardcoded here. Set them as real environment variables (same pattern
 * as DB_HOST/DB_USER/etc. in site/config.php - see docker-compose.yml
 * for a local example, or your hosting control panel for shared hosting).
 * With none of the three set, Stripe payments are simply unconfigured.
 */

$stripe_product_key = getenv('STRIPE_PRODUCT_KEY') ?: '';
$stripe_api_key_public = getenv('STRIPE_API_KEY_PUBLIC') ?: '';
$stripe_api_key_secret = getenv('STRIPE_API_KEY_SECRET') ?: '';

?>