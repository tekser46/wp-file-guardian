# WP File Guardian

**WP File Guardian** is an advanced WordPress security and maintenance plugin designed to help site owners detect suspicious changes, scan files and database content, monitor login activity, calculate risk levels, and protect critical website assets from malware, unauthorized modifications, and operational failures.

It is built for administrators who want a practical, centralized security layer inside WordPress without relying on multiple disconnected tools.

---

## Overview

WordPress websites are common targets for malware injections, file tampering, brute-force login attempts, hidden backdoors, database spam, and unauthorized code changes.  
WP File Guardian helps you detect these threats early, investigate them faster, and take action directly from the WordPress admin panel.

The plugin combines:

- file integrity monitoring
- malware pattern detection
- database scanning
- login protection
- risk scoring
- quarantine and repair workflows
- backup support
- audit visibility

All in one unified security dashboard.

---

## Key Features

### 1. File Scanner
Scan WordPress core files, plugins, themes, and uploads for suspicious patterns such as:

- obfuscated PHP code
- `eval`, `base64_decode`, `gzinflate`, `str_rot13`, and similar risky functions
- hidden shells and backdoors
- unexpected executable files in unsafe locations
- modified core files
- recently changed files
- unauthorized PHP files inside uploads directories

This helps identify malware injections and suspicious code before they cause further damage.

---

### 2. Database Scanner
Detect potentially malicious or suspicious content inside the WordPress database, including:

- injected script tags
- hidden spam links
- encoded payloads
- suspicious admin options
- altered site URLs or redirect entries
- rogue cron entries
- suspicious content inside posts, options, widgets, and metadata

This is especially useful when attackers inject malware into the database instead of the filesystem.

---

### 3. File Integrity Monitoring
Monitor critical WordPress files and detect changes over time.  
WP File Guardian can help you answer questions such as:

- Which file changed?
- When did it change?
- Was it expected or suspicious?
- Is the modified file part of WordPress core, a plugin, or a theme?

This makes incident response faster and more structured.

---

### 4. Login Guard
Protect the WordPress login area against abuse and suspicious access attempts with features such as:

- failed login tracking
- suspicious IP detection
- brute-force indicators
- admin account activity visibility
- abnormal login behavior monitoring
- optional lockout or alert logic

This reduces the risk of unauthorized access to your admin panel.

---

### 5. Risk Score Engine
WP File Guardian calculates a risk score based on detected indicators across the website.

Examples of risk factors may include:

- suspicious code signatures
- core file modifications
- abnormal login attempts
- executable files in upload folders
- database injection patterns
- multiple simultaneous warning signals

This gives administrators a fast way to understand whether the site is in a low, medium, high, or critical security state.

---

### 6. Quarantine and Repair Workflow
Instead of immediately deleting suspicious files, WP File Guardian is designed to support safer handling options such as:

- quarantining suspicious files
- reviewing flagged items before deletion
- repairing changed files
- restoring known-good versions
- isolating dangerous files from public execution

This helps reduce accidental damage during cleanup.

---

### 7. Backup Awareness and Recovery Support
Security incidents often require rollback capability. WP File Guardian supports a security workflow that includes backup awareness and recovery-oriented operations.

Depending on your project configuration, the plugin can be extended for:

- local backups
- remote backup storage
- recovery preparation
- rollback assistance after infection cleanup

---

### 8. Security Logs and Audit Trail
Track relevant activity in one place, including:

- scan results
- detected risks
- file changes
- cleanup actions
- login events
- repair operations
- backup-related events

A clear audit trail makes troubleshooting, documentation, and recovery much easier.

---

### 9. Centralized WordPress Admin Dashboard
All important security actions can be managed from a central admin interface:

- scan status
- risk level
- suspicious files
- database alerts
- login anomalies
- recommendations
- cleanup actions
- logs and reports

This avoids jumping between multiple plugins and external tools.

---

## Why WP File Guardian?

Many WordPress site owners only realize they have been compromised after:

- Google blacklists the website
- visitors report strange redirects
- hosting providers suspend the account
- SEO traffic collapses
- unknown admin users appear
- malware reinfects the site after incomplete cleanup

WP File Guardian is designed to reduce that delay and give administrators practical visibility before a small issue becomes a major incident.

---

## Typical Use Cases

WP File Guardian is useful for:

- agencies managing multiple WordPress sites
- freelancers maintaining client websites
- business owners who need basic security oversight
- administrators recovering infected WordPress installations
- developers who want to track unexpected file changes
- site operators looking for a combined file + database security workflow

---

## What It Can Help Detect

WP File Guardian can help identify indicators such as:

- malware injections
- hidden PHP backdoors
- cloaked redirect code
- SEO spam injections
- suspicious admin activity
- unauthorized file modifications
- malicious code inside database records
- risky plugin or theme file changes
- exploit leftovers after partial cleanup

---

## How It Works

The plugin follows a layered security workflow:

1. **Scan**  
   It analyzes files and database content for suspicious indicators.

2. **Compare**  
   It checks changes against expected WordPress structures and known patterns.

3. **Score**  
   It assigns a risk level based on the severity and number of detections.

4. **Report**  
   It displays findings in the WordPress admin dashboard.

5. **Act**  
   It allows administrators to review, quarantine, repair, or remove suspicious items.

6. **Monitor**  
   It keeps track of new changes, login anomalies, and recurring security events.

---

## Main Modules

### File Protection Module
Focused on code-level threats in:

- WordPress core
- themes
- plugins
- uploads
- custom directories

### Database Protection Module
Focused on suspicious content stored in:

- `wp_options`
- `wp_posts`
- `wp_postmeta`
- widgets
- transients
- custom tables

### Access Protection Module
Focused on:

- login abuse
- suspicious authentication patterns
- admin-related anomalies

### Recovery Module
Focused on:

- quarantine
- repair
- cleanup
- backup-aware response

---

## Installation

1. Upload the plugin folder to your `/wp-content/plugins/` directory  
   **or**
2. Install the plugin through the WordPress admin panel
3. Activate **WP File Guardian**
4. Open the plugin dashboard from the WordPress admin menu
5. Run the first full scan
6. Review the risk report and recommendations

---

## Recommended First Steps After Installation

After activation, it is recommended to:

1. Run a full file scan
2. Run a database scan
3. Review the highest-risk findings first
4. Check modified core files
5. Inspect suspicious files in the uploads directory
6. Review login-related warnings
7. Create or verify backups before cleanup
8. Quarantine instead of deleting when unsure

---

## Security Philosophy

WP File Guardian is built around three principles:

### Visibility
You should be able to see what changed, where it changed, and why it matters.

### Safety
Potentially dangerous files should be reviewed or isolated before destructive actions are taken.

### Recoverability
A good security workflow is not only about detection, but also about rollback, repair, and controlled cleanup.

---

## Performance Considerations

Security scans can be resource-intensive on large websites. WP File Guardian is intended to be implemented with performance-aware strategies such as:

- batched processing
- scheduled scans
- selective directory analysis
- controlled file access
- limited-depth scanning where needed
- safe timeout handling

This helps keep the admin experience usable even on larger WordPress installations.

---

## Best Used Together With

For stronger WordPress security, WP File Guardian should be part of a broader hardening strategy that includes:

- strong admin passwords
- two-factor authentication
- reliable backups
- hosting-level malware protection
- up-to-date plugins and themes
- minimal plugin footprint
- restricted file permissions
- disabled unused accounts
- least-privilege admin practices

---

## Important Notes

WP File Guardian is designed to assist with security analysis and incident response, but no security plugin can guarantee complete protection against every attack method.

It should be used as part of a layered security strategy, not as a single point of trust.

Always verify suspicious findings before deleting files or database entries, especially on production websites.

---

## Roadmap

Planned and expandable areas may include:

- scheduled automatic scans
- email alerts and notifications
- advanced quarantine manager
- one-click repair workflows
- remote backup integrations
- whitelist / ignore rules
- scan profiles
- multisite support
- REST API endpoints
- exportable scan reports
- threat signature updates
- deeper integrity baselines

---

## Who This Plugin Is For

WP File Guardian is ideal for users who want:

- a serious WordPress security utility
- visibility into file and database threats
- faster detection of suspicious changes
- actionable risk analysis
- recovery-oriented workflows
- a centralized admin security dashboard

---

## Development Goals

The long-term goal of WP File Guardian is to become a professional WordPress security operations plugin that combines:

- detection
- monitoring
- risk analysis
- incident response
- recovery support

inside a single WordPress-native system.

---

## Contributing

Contributions, ideas, bug reports, and feature suggestions are welcome.

You can contribute by:

- reporting bugs
- suggesting improvements
- improving detection rules
- optimizing scan performance
- refining the admin UX
- submitting pull requests

---

## License

This project can be distributed under the license defined in the repository.

If no license file is included yet, add the appropriate open-source or commercial license before public release.

---

## Disclaimer

WP File Guardian helps detect suspicious activity and supports investigation and cleanup workflows, but it does not replace full server security, secure hosting practices, or professional incident response in severe compromise cases.

Always keep backups and verify findings carefully before making irreversible changes.

---

## Support the Project

If you find this project useful, consider starring the repository, sharing feedback, and contributing ideas for future versions.

WP File Guardian is built to make WordPress security more transparent, manageable, and recovery-friendly.
