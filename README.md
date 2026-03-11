# WP File Guardian

**WP File Guardian** is an advanced WordPress security and maintenance plugin designed to help site owners detect suspicious changes, scan files and database content, monitor login activity, calculate risk levels, and protect critical website assets from malware, unauthorized modifications, and operational failures.

It is built for administrators who want a practical, centralized security layer inside WordPress without relying on multiple disconnected tools.

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6+ or MariaDB 10.1+

---

## Installation

1. Download the latest release (`wp-file-guardian.zip`) from the repository
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Click **Activate**
5. The **WP File Guardian** menu will appear in the WordPress admin sidebar

**Manual installation:**
1. Extract the zip and upload the `wp-file-guardian` folder to `/wp-content/plugins/`
2. Activate the plugin from the **Plugins** page

---

## Quick Start Guide

After activation, follow these steps:

1. Go to **WP File Guardian > Dashboard** to see the security overview
2. Run a **Full Scan** to analyze all WordPress files
3. Run a **Database Scan** to check for malicious database entries
4. Review the **Security Score** to understand your site's overall health
5. Enable **Security Hardening** measures for immediate protection
6. Set up **Two-Factor Authentication** for admin accounts
7. Configure **Firewall Rules** to block known threats

---

## Features & Usage Guide

### 1. File Scanner

**Location:** WP File Guardian > Scanner

Scans WordPress core files, plugins, themes, and uploads for suspicious patterns such as obfuscated PHP code, `eval`/`base64_decode` calls, hidden shells, backdoors, and unauthorized executable files.

**How to use:**
- Click **Full Scan** to scan all WordPress files (recommended for first use)
- Click **Quick Scan** to scan only recently modified files
- Review results sorted by severity: Critical, Warning, Info, Notice
- Use the checkboxes to select multiple files, then apply bulk actions (Quarantine, Ignore, Delete)
- Click individual file actions to quarantine, view details, or delete specific files

**Scan results are color-coded:**
- **Critical (red):** Likely malware or backdoor, action needed immediately
- **Warning (orange):** Suspicious pattern detected, manual review recommended
- **Info (blue):** Informational finding, usually low risk
- **Notice (gray):** Minor observation, no action needed

---

### 2. Database Scanner

**Location:** WP File Guardian > DB Scanner

Detects potentially malicious or suspicious content inside the WordPress database including injected script tags, hidden spam links, encoded payloads, suspicious admin options, altered site URLs, and rogue cron entries.

**How to use:**
- Click **Start DB Scan** to begin scanning database tables
- Results show the affected table, row, and type of suspicious content
- Use the action buttons on each finding:
  - **View:** See the actual content of the suspicious entry
  - **Clean:** Remove or sanitize the malicious content
  - **Ignore:** Mark the finding as a false positive and hide it

---

### 3. File Integrity Monitoring

**Location:** WP File Guardian > Monitor

Monitors critical WordPress files and detects changes over time. Tracks which files changed, when they changed, and whether the change was expected.

**How to use:**
- The monitor runs automatically on a configurable schedule
- View the list of detected file changes with timestamps
- Compare file changes against known WordPress core checksums
- Receive email notifications when critical files are modified

---

### 4. Login Guard

**Location:** WP File Guardian > Login Guard

Protects the WordPress login area against brute-force attacks and suspicious access attempts.

**How to use:**
- View failed login attempts with IP addresses and timestamps
- See which usernames attackers are trying
- Monitor suspicious IP addresses
- Automatic email notifications are sent when unauthorized login attempts are detected
- Configure lockout thresholds in the settings

---

### 5. Security Score

**Location:** WP File Guardian > Security Score

Calculates an overall security grade (A+ through F) based on detected risks across your entire website.

**How to use:**
- Visit the Security Score page to see your current grade and numeric score
- The score gauge visually shows your security level with color coding
- Review the breakdown of risk factors contributing to your score
- Follow the recommendations to improve your grade
- Re-scan after making changes to see your updated score

**Score ranges:**
- **A+ / A (90-100):** Excellent security posture
- **B (80-89):** Good, minor improvements possible
- **C (70-79):** Fair, several issues need attention
- **D (60-69):** Poor, significant risks detected
- **F (below 60):** Critical, immediate action required

---

### 6. Security Hardening

**Location:** WP File Guardian > Hardening

One-click security hardening measures to strengthen your WordPress installation.

**Available measures:**
| Measure | Description |
|---------|-------------|
| **Disable File Editor** | Disables the built-in WordPress theme and plugin file editor to prevent code changes from the admin area |
| **Disable XML-RPC** | Disables XML-RPC and removes the X-Pingback header. Prevents brute-force and DDoS attacks via XML-RPC |
| **Security Headers** | Adds X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, and Permissions-Policy headers |
| **Block PHP in Uploads** | Adds an .htaccess rule to prevent PHP execution inside the uploads directory |
| **Restrict REST API** | Blocks REST API access for unauthenticated visitors. Logged-in users are unaffected |
| **Hide WordPress Version** | Removes the WordPress version from the page source, RSS feeds, and enqueued asset URLs |

**How to use:**
- Click **Enable** next to each measure to activate it
- Click **Disable** to deactivate a previously enabled measure
- The status badge shows **Active** (blue) or **Inactive** (gray)
- Use the **Test Server Status** button to verify your server configuration after changes

---

### 7. Firewall / WAF

**Location:** WP File Guardian > Firewall

A Web Application Firewall that blocks malicious requests before they reach WordPress.

**How to use:**
- View the **Firewall Overview** dashboard showing blocked requests today, this week, active rules, and auto-banned IPs
- **Add a rule:** Select a rule type (IP Blacklist, IP Whitelist, Country Block, or User Agent Block), enter the value, and click **Add Rule**
- **Manage rules:** Toggle rules on/off, or delete rules from the Active Rules table
- **View logs:** Check the firewall log to see blocked requests with timestamps, IPs, and reasons

**Rule types:**
| Type | Value Example | Description |
|------|---------------|-------------|
| IP Blacklist | `192.168.1.100` or `10.0.0.0/8` | Block a specific IP or CIDR range |
| IP Whitelist | `203.0.113.50` | Always allow this IP (bypasses all rules) |
| Country Block | `CN`, `RU` | Block all traffic from a country (ISO code) |
| UA Block | `BadBot` | Block requests matching a User Agent pattern |

---

### 8. Vulnerability Scanner

**Location:** WP File Guardian > Vulnerabilities

Scans installed plugins and themes against known vulnerability databases.

**How to use:**
- Click **Start Vulnerability Scan** to check all installed plugins and themes
- Review the results categorized by severity: Critical, High, Medium, Low
- See details about each vulnerability and available updates
- Click **Update** to update vulnerable plugins/themes directly
- Click **Ignore** to dismiss known/accepted vulnerabilities

---

### 9. Two-Factor Authentication (2FA)

**Location:** WP File Guardian > Two-Factor

Adds TOTP-based two-factor authentication to WordPress accounts using apps like Google Authenticator, Authy, or Microsoft Authenticator.

**How to enable 2FA:**
1. Go to the Two-Factor page
2. Click **Enable 2FA**
3. A QR code and manual entry key will be displayed
4. Scan the QR code with your authenticator app
5. **Save the backup codes** displayed on screen (store them securely)
6. Enter a code from your authenticator app in the **Test Your Code** field and click **Verify** to confirm setup

**How to use after setup:**
- When logging in, enter your password as usual
- You will be prompted for a 6-digit verification code
- Open your authenticator app and enter the current code
- If you lose your device, use one of the backup codes

**Admin management:**
- View 2FA status for all users in the users table
- Admins can disable 2FA for other users if needed
- Regenerate backup codes if they are lost or compromised

---

### 10. File Permissions

**Location:** WP File Guardian > Permissions

Checks file and directory permissions for security issues (Linux/Unix servers only).

**How to use:**
- Click **Check Permissions** to scan all WordPress files and directories
- Results show files with insecure permissions (e.g., world-writable files)
- Click **Fix** to automatically correct permissions to recommended values
- Use **Fix All** for bulk permission correction

**Recommended permissions:**
| Item | Permission |
|------|-----------|
| Directories | `755` |
| Files | `644` |
| wp-config.php | `600` |
| .htaccess | `644` |

> **Note:** This feature is not available on Windows servers, as they use NTFS ACLs instead of Unix-style permissions.

---

### 11. Quarantine

**Location:** WP File Guardian > Quarantine

Safely isolates suspicious files instead of deleting them immediately.

**How to use:**
- Quarantined files are moved to a secure directory and made non-executable
- View all quarantined files with original path, date, and reason
- Click **Restore** to return a file to its original location
- Click **Delete** to permanently remove a quarantined file
- Files are automatically quarantined when critical threats are detected (if enabled in settings)

---

### 12. Backup & Repair

**Location:** WP File Guardian > Backups / Repair

Create security-oriented backups and repair modified WordPress core files.

**Backups:**
- Click **Create Backup** to create a backup of critical files
- Download or restore from previous backups
- Backups include a snapshot of the current file state before cleanup

**Repair:**
- Compare installed WordPress core files against official checksums
- Click **Repair** to restore modified core files to their original versions
- Reinstall plugins or themes from the WordPress.org repository

---

### 13. Logs & Audit Trail

**Location:** WP File Guardian > Logs

Track all security-related activity in one place.

**Logged events include:**
- Scan results and completion times
- File quarantine and restore actions
- Login attempts (successful and failed)
- Hardening changes (enabled/disabled)
- Firewall rule changes and blocked requests
- Backup and repair operations
- 2FA enable/disable events

**How to use:**
- Filter logs by type, date range, or severity
- Export logs for external analysis or compliance
- Clear old logs to free database space

---

### 14. Email Notifications

WP File Guardian sends email notifications for important security events:

| Event | Description |
|-------|-------------|
| **Scan Complete** | Summary report after a full or quick scan finishes, including total files scanned, critical/warning/info counts |
| **Unauthorized Login** | Alert when a failed login attempt is detected from a suspicious IP |

**Configuration:**
- Notification settings can be configured in **WP File Guardian > Settings**
- Emails are sent to the WordPress admin email by default
- Scan emails are sent only once per scan (not per file)

---

### 15. Settings

**Location:** WP File Guardian > Settings

Configure plugin behavior:

- **Scan settings:** File extensions to scan, directories to exclude, scan depth
- **Quarantine settings:** Auto-quarantine critical files, quarantine directory
- **Notification settings:** Email recipients, notification types
- **Schedule settings:** Automatic scan frequency
- **General settings:** Plugin capability requirements, cleanup on uninstall

---

## WP-CLI Support

WP File Guardian includes WP-CLI commands for automation:

```bash
# Run a full scan
wp wpfg scan --type=full

# Run a quick scan
wp wpfg scan --type=quick

# View scan results
wp wpfg results

# Create a backup
wp wpfg backup create
```

---

## How It Works

The plugin follows a layered security workflow:

1. **Scan** - Analyzes files and database content for suspicious indicators
2. **Compare** - Checks changes against expected WordPress structures and known patterns
3. **Score** - Assigns a risk level based on the severity and number of detections
4. **Report** - Displays findings in the WordPress admin dashboard
5. **Act** - Allows administrators to review, quarantine, repair, or remove suspicious items
6. **Monitor** - Keeps track of new changes, login anomalies, and recurring security events

---

## Security Philosophy

WP File Guardian is built around three principles:

- **Visibility:** You should be able to see what changed, where it changed, and why it matters
- **Safety:** Potentially dangerous files should be reviewed or isolated before destructive actions are taken
- **Recoverability:** A good security workflow is not only about detection, but also about rollback, repair, and controlled cleanup

---

## Performance Considerations

Security scans can be resource-intensive on large websites. WP File Guardian uses:

- Batched processing to avoid timeouts
- Scheduled scans during low-traffic periods
- Selective directory analysis
- Safe timeout handling

This helps keep the admin experience usable even on larger WordPress installations.

---

## Typical Use Cases

WP File Guardian is useful for:

- Agencies managing multiple WordPress sites
- Freelancers maintaining client websites
- Business owners who need basic security oversight
- Administrators recovering infected WordPress installations
- Developers who want to track unexpected file changes

---

## Contributing

Contributions, ideas, bug reports, and feature suggestions are welcome.

You can contribute by:

- Reporting bugs
- Suggesting improvements
- Improving detection rules
- Optimizing scan performance
- Submitting pull requests

---

## License

This project is licensed under the **GNU General Public License v2.0 or later** (GPL-2.0-or-later).

See the [LICENSE](LICENSE) file for the full license text.

---

## Disclaimer

WP File Guardian helps detect suspicious activity and supports investigation and cleanup workflows, but it does not replace full server security, secure hosting practices, or professional incident response in severe compromise cases.

Always keep backups and verify findings carefully before making irreversible changes.

---

## Support the Project

If you find this project useful, consider starring the repository, sharing feedback, and contributing ideas for future versions.

WP File Guardian is built to make WordPress security more transparent, manageable, and recovery-friendly.
