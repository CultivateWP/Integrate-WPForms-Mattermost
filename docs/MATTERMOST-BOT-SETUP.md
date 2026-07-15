# Mattermost Bot Setup

This plugin posts messages through a Mattermost bot account. Use a dedicated bot with the default **Member** role and add it only to the teams and channels where WPForms should post.

## 1. Enable bot account creation

This step requires a Mattermost System Admin. If **Bot Accounts** is already available in the Integrations menu, skip to the next section.

1. Open **System Console → Integrations → Bot Accounts**.
2. Set **Enable Bot Account Creation** to **true**.
3. Save the setting.

If you cannot access the System Console, ask your Mattermost administrator to enable bot accounts or create the bot for you.

## 2. Create the bot and copy its token

1. Open the Mattermost **Product** menu.
2. Select **Integrations → Bot Accounts**.
3. Select **Add Bot Account**.
4. Enter a username such as `wpforms` and a clear display name such as `WPForms Notifications`.
5. Keep the role set to **Member**. This integration does not require the System Admin role.
6. Select **Create Bot Account**.
7. Copy the generated **bot access token** immediately and store it securely. The access token is not the Token ID and will not be displayed again after creation.

## 3. Give the bot access to destinations

Add the bot account to each Mattermost team and channel where it should be allowed to post. Private channels require the bot to be added explicitly.

The plugin only lists teams and channels visible to the bot. If a destination is missing later, confirm that the bot is a member, then use **WPForms → Mattermost Logs → Test connection and refresh channels**.

## 4. Connect WordPress

In WordPress, go to **WPForms → Settings → Integrations → Mattermost** and enter:

- **Mattermost URL:** The site URL, such as `https://mattermost.example.com`. Do not include `/api/v4`.
- **Bot token:** The bot access token copied when the bot was created.

Connecting verifies the bot token and loads its available teams and channels. Then edit a form and open **Marketing → Mattermost** to add a form connection.

## Security notes

- Treat the bot token like a password. Do not place it in form messages, logs, support tickets, screenshots, or source control.
- Keep the bot as a Member and limit its team/channel membership to the destinations this plugin needs.
- If a token is exposed, revoke it in Mattermost, generate a replacement, and reconnect the integration.
- For configuration-managed sites, credentials can be supplied through `IWMM_MATTERMOST_URL` and `IWMM_MATTERMOST_TOKEN` constants in `wp-config.php`.

See Mattermost's official [Bot accounts documentation](https://developers.mattermost.com/integrate/reference/bot-accounts/) for current administration details.
