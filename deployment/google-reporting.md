# Google Search Console and GA4 reporting setup

The Admin Google Performance Reports page uses Google's read-only Search Console and Analytics Data APIs. The existing Google verification meta token and GA4 Measurement ID are for site verification and browser tracking; they do not grant API reporting access.

## 1. Google Cloud setup

1. Create or select a Google Cloud project.
2. Enable **Google Search Console API** and **Google Analytics Data API**.
3. Configure the OAuth consent screen for the Google account(s) that will administer reports.
4. Create an OAuth 2.0 **Web application** client.
5. Add this exact authorized redirect URI (adjust only if the production domain/path changes):

   `https://alomsenglishcenter.com/admin/seo/google/callback`

6. Copy the OAuth client ID and client secret into the production environment. Do not paste secrets into GitHub or the admin SEO form.

## 2. Production environment

Set these values in the hosting account's environment configuration:

```dotenv
GOOGLE_CLIENT_ID=your-google-oauth-client-id
GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret
GOOGLE_REDIRECT_URI=https://alomsenglishcenter.com/admin/seo/google/callback
```

Keep the existing `APP_KEY` stable and private. The Google refresh token is encrypted with Laravel Crypt before it is stored in the settings table.

## 3. Admin setup

1. Open Admin → SEO & Marketing.
2. Enter the exact verified Search Console property, either `https://alomsenglishcenter.com/` or `sc-domain:alomsenglishcenter.com`.
3. Enter the numeric GA4 Property ID (not the `G-...` Measurement ID).
4. Save SEO settings.
5. Open Google Performance Reports and connect the Google account that has at least read access to the configured Search Console and GA4 properties.

The dashboard requests the last 28 complete days. It displays Search Console top queries and GA4 users, sessions, event count and total revenue. A missing property or API permission is shown as unavailable rather than replaced with estimated data.

## 4. Disconnect

Use **Disconnect Google** in the reports page to remove the locally stored encrypted refresh token and cached access token. This does not revoke the app from the Google Account's third-party access page; revoke it there as well if you want to remove Google's authorization completely.
