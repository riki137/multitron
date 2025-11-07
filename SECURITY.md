# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in Multitron, please report it privately:

**Email:** popelis@efabrica.sk

**Please include:**
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

**Response Time:**
- We aim to acknowledge reports within 48 hours
- We'll provide a more detailed response within 7 days
- Security patches will be released as soon as possible

## Security Considerations

### Process Execution
Multitron spawns worker processes using Inter-Process Communication (IPC). Ensure:
- Task factories don't execute untrusted code
- Command-line options are validated in your tasks
- Worker processes run with appropriate system permissions

### Inter-Process Communication
- IPC data is serialized between processes
- Validate data received from shared cache
- Don't store sensitive data in the shared cache without encryption

### Resource Limits
- Set appropriate memory limits using `-m` option
- Monitor worker process resource usage
- Implement timeouts for long-running tasks

## Best Practices

1. **Validate Input:** Always validate and sanitize data in your tasks
2. **Principle of Least Privilege:** Run workers with minimal required permissions
3. **Keep Updated:** Use the latest stable version
4. **Monitor Logs:** Review task output for suspicious activity
5. **Secure Dependencies:** Keep dependencies updated

## Disclosure Policy

- Security issues will be disclosed after a patch is available
- We'll credit researchers who report valid vulnerabilities (unless they prefer anonymity)
- CVE IDs will be requested for significant vulnerabilities

Thank you for helping keep Multitron secure! 🔒
