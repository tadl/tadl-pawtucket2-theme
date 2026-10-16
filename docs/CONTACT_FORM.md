# Contact form and Turnstile

The Contact form and object Item Inquiry use the same theme controller. Mail goes
to `contact_email` in `conf/contact.conf`. The site-configured sender remains From;
the validated visitor email and normalized name become Reply-To. Every submission
has a unique `CF-` subject reference to separate unrelated mail conversations.

## Create a Cloudflare widget

Cloudflare's [Turnstile Free plan](https://developers.cloudflare.com/turnstile/plans/)
currently includes unlimited challenges, up to 20 widgets and 10 hostnames per
widget. It works without Cloudflare DNS or proxying. No PHP package is needed;
the integration uses PHP cURL, as native Pawtucket CAPTCHA verification does.

1. Open **Turnstile** in the Cloudflare account dashboard and select **Add widget**.
2. Give it a descriptive name, such as the site's contact form.
3. Add every hostname where the form will be available. Use bare hostnames,
   without a scheme, path or port. Future aliases may be included before their
   DNS cutover. Use one widget/key pair for the site's permitted aliases.
4. Select **Managed** mode and leave pre-clearance disabled. Managed mode usually
   verifies automatically and can request a checkbox; it does not use image puzzles.
5. Create the widget and retain its site key and secret key securely.

See Cloudflare's [dashboard instructions](https://developers.cloudflare.com/turnstile/get-started/widget-management/dashboard/)
and [widget modes](https://developers.cloudflare.com/turnstile/concepts/widget/).

## Enable it on the server

After deploying the complete theme revision, edit the installation's private
`setup.php`, typically `/var/www/pawtucket2/setup.php`. Preserve a private backup
outside the web root before editing. Add these constants alongside the other
settings, before the final `require` of `app/helpers/post-setup.php`:

```php
define('__TADL_TURNSTILE_SITE_KEY__', 'REPLACE_WITH_SITE_KEY');
define('__TADL_TURNSTILE_SECRET_KEY__', 'REPLACE_WITH_SECRET_KEY');
define('__TADL_TURNSTILE_HOSTNAMES__', [
    'archive.example.org',
    'history.example.org',
]);
```

Replace the sample hostnames with the same hostnames allowed in Cloudflare. Do not
commit real keys, copy the secret into a theme file, or expose it in browser HTML.
The existing `setup.php` is outside the theme deployment and remains readable by
the web process under the installation's existing permission policy. A shell-only
environment variable is not required. Check syntax without printing its contents:

```sh
php -l /var/www/pawtucket2/setup.php
```

Either non-empty key selects Turnstile for these forms. Both keys and a nonempty
valid hostname list are required to send anonymous submissions. Partial setup
blocks anonymous sending and shows retry guidance. With no keys, existing
arithmetic/reCAPTCHA behavior remains. Turnstile replaces those controls when
enabled; no change to `check_security` is necessary. To revert, remove all three
Turnstile constants; the configured legacy challenge resumes.

No cache purge or Apache restart is part of this change; follow the normal theme
deployment's PHP OPcache timestamp policy. These constants affect Contact and Item
Inquiry only. Login/registration challenges retain native configuration.

## Verification and failure behavior

Only anonymous visitors receive the widget; signed-in users keep the existing
CAPTCHA exemption. The widget uses flexible width, keyboard-accessible Cloudflare
controls, automatic retry and token refresh. It submits the native
`cf-turnstile-response` field along with the form. No custom JavaScript is needed.

The server checks POST, native CSRF and required/email fields, then verifies the
token against Cloudflare's fixed HTTPS Siteverify endpoint. It requires HTTP 200,
boolean success, action `contact` and a hostname in the explicit allowlist. Missing,
expired, replayed or rejected tokens, incorrect host/action, malformed responses,
TLS/network failures and an eight-second request timeout prevent mail. Form values
remain available on retry, with a fresh widget. The browser and server must be able
to reach `challenges.cloudflare.com`; a restrictive CSP must permit Cloudflare's
[required script/frame sources](https://developers.cloudflare.com/turnstile/reference/content-security-policy/).

Server verification sends only the token and secret, without contact fields or an
optional visitor-IP parameter. Cloudflare's browser widget processes its own
verification signals. The secret, tokens and submissions are not logged by this
integration. Visitors without JavaScript receive instructions to enable it;
verification failures provide retry guidance. The form omits the email-help line.

After deployment and configuration:

- Open Contact anonymously on each hostname once it serves this installation.
  Confirm the widget replaces the math question and fits phone and desktop widths.
- Perform an operator-authorized test send; check delivery, named Reply-To and the
  distinct subject. A browser widget alone is not proof of server verification.
- Confirm missing/rejected verification does not send, preserves the message and
  lets the visitor retry. Confirm signed-in forms do not show a challenge.
- Check widget analytics in Cloudflare after real use. Do not copy real submissions,
  verification tokens or secrets into reports or source control.

The standalone contact suite covers the actual controller, form/mail templates and
verification rules with a synthetic HTTP boundary and captured mail, including two
permitted hostnames. It does not contact Cloudflare or send mail:

```sh
php tests/contact_mail_test.php
```
