	# Updating

## One-click Updating

When an update is available, users with the permission to update Craft will see a badge in the CP header. Clicking on that badge will take you to a page that shows the release notes of all the available updates.

You can get to that page at any time by clicking the “Check for updates” link in the CP footer as well. Whenever you go to the page, whether Craft thinks updates are available or not, the page will clear its cache and re-check for available updates.

At the top of the page there is an “Update” button. Clicking that will initiate Craft Commerce’s self-updating process.

For one-click updates to work, your plugins folder and all its enclosed files and folders must be writable. The exact permissions you should use depend on the relationship between the user that Apache/PHP is running as and the user who actually owns the craft/config folder.

Here are some recommended permissions depending on that relationship:

If they are the same user, use 744.
If they're in the same group, then use 774.
Otherwise, use 777.

## Manually Updating

If you’re manually updating a live site, we recommend you follow these instructions to minimize the time your site is down:

1. Backup your entire Craft database.
2. Rename the plugins/commerce/ folder in the latest release zip to “commerce-new”.
3. Upload plugins/commerce-new to the craft/plugins folder on your server, alongsite the old plugins/commerce folder.
4. Once commerce-new folder is done uploading proceed to the next step.
5. Rename the old craft/plugins/commerce folder to craft/plugins/commerce-old.
6. Rename commerce-new to “commerce”.
7. Point your browser to your Craft control panel. You will be prompted to proceed with a database update.
Click “Finish up” and let the database updates run.
8. If all is well, delete the commerce-old folder, otherwise restore this folder as "commerce" and restore your database from backup and contact support.

# Upgrading from Market Commerce

Craft Commerce is the successor to Market Commerce. To upgrade from Market Commerce to Craft Commerce, [follow these instructions](upgrading-from-market.md).
