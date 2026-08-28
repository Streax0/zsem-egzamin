/**
 * ============================================================================
 * ZSEM Tech Platform — Advanced Web CLI Terminal Simulator & CKE Lab Engine
 * Version: 2.5.0 (Beta 2)
 *
 * Capabilities:
 * - Full Dual-OS Engine: Linux (Bash/GNU) & Windows (CMD / PowerShell 7)
 * - In-Memory Virtual File System (VFS) with Node Tree, Permissions, Symlinks & Persistence
 * - Simulated Virtual Network Stack: IP/Subnet, Default Gateway, DNS, Routing & Firewall
 * - Service Manager & Daemon Engine (systemctl, service, sc, net start/stop, iisreset)
 * - 35+ Multi-Step Practical CKE Exam Scenarios (INF.02, INF.03, INF.08)
 * - Rich "Step-by-Step" Pedagogical Guide Card with Collapsible Mini-Bar & Hint Drawer
 * - Integrated GNU Nano 6.2 In-Browser Text Editor Overlay with Keybindings
 * - 6 Interactive Sub-Shells: PowerShell, DiskPart, MySQL, Nslookup, Python REPL, SSH
 * - Real Command Pipeling (|), Redirections (>, >>), Arguments Parsing & Exit Codes
 * - Gamified Progression with XP Reward Dispatch, Badges & Local Storage Persistence
 * ============================================================================
 */

(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════════════════════
    // 1. IN-MEMORY VIRTUAL FILE SYSTEM (VFS)
    // ════════════════════════════════════════════════════════════════════════════

    class VirtualFileSystem {
        constructor() {
            this.storageKey = 'zsem_vfs_data_v2';
            this.currentDirLinux = '/home/student';
            this.currentDirWin = 'C:\\Users\\Student';
            this.root = this.load() || this.defaultFileSystem();
        }

        defaultFileSystem() {
            const now = new Date().toISOString();
            return {
                linux: {
                    type: 'dir',
                    name: '/',
                    permissions: '0755',
                    owner: 'root',
                    group: 'root',
                    created: now,
                    modified: now,
                    children: {
                        'bin': {
                            type: 'dir', name: 'bin', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'bash': { type: 'file', name: 'bash', permissions: '0755', owner: 'root', group: 'root', size: 1183448 },
                                'ls': { type: 'file', name: 'ls', permissions: '0755', owner: 'root', group: 'root', size: 142144 },
                                'cat': { type: 'file', name: 'cat', permissions: '0755', owner: 'root', group: 'root', size: 43456 },
                                'chmod': { type: 'file', name: 'chmod', permissions: '0755', owner: 'root', group: 'root', size: 64200 },
                                'su': { type: 'file', name: 'su', permissions: '4755', owner: 'root', group: 'root', size: 67800 }
                            }
                        },
                        'etc': {
                            type: 'dir', name: 'etc', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'passwd': {
                                    type: 'file', name: 'passwd', permissions: '0644', owner: 'root', group: 'root', size: 1850,
                                    content: "root:x:0:0:root:/root:/bin/bash\ndaemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin\nbin:x:2:2:bin:/bin:/usr/sbin/nologin\nsys:x:3:3:sys:/dev:/usr/sbin/nologin\nsync:x:4:65534:sync:/bin:/bin/sync\nwww-data:x:33:33:www-data:/var/www:/usr/sbin/nologin\nbind:x:110:115::/var/cache/bind:/usr/sbin/nologin\npostfix:x:111:116::/var/spool/postfix:/usr/sbin/nologin\nstudent:x:1000:1000:ZSEM Student,,,:/home/student:/bin/bash\n"
                                },
                                'shadow': {
                                    type: 'file', name: 'shadow', permissions: '0640', owner: 'root', group: 'shadow', size: 820,
                                    content: "root:$6$vQ9j7d$k3l2...:19500:0:99999:7:::\nstudent:$6$zsem2026$8mXy...:19500:0:99999:7:::\n"
                                },
                                'group': {
                                    type: 'file', name: 'group', permissions: '0644', owner: 'root', group: 'root', size: 640,
                                    content: "root:x:0:\nsudo:x:27:student\nwww-data:x:33:\nadm:x:4:student\nadmin:x:110:\nstudent:x:1000:\n"
                                },
                                'hostname': {
                                    type: 'file', name: 'hostname', permissions: '0644', owner: 'root', group: 'root', size: 9,
                                    content: "zsem-lab\n"
                                },
                                'hosts': {
                                    type: 'file', name: 'hosts', permissions: '0644', owner: 'root', group: 'root', size: 210,
                                    content: "127.0.0.1\tlocalhost\n127.0.1.1\tzsem-lab\n192.168.1.100\tzsem-srv.local\twww.zsem.local\n"
                                },
                                'resolv.conf': {
                                    type: 'file', name: 'resolv.conf', permissions: '0644', owner: 'root', group: 'root', size: 75,
                                    content: "nameserver 192.168.1.1\nnameserver 8.8.8.8\nsearch zsem.local\n"
                                },
                                'network': {
                                    type: 'dir', name: 'network', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'interfaces': {
                                            type: 'file', name: 'interfaces', permissions: '0644', owner: 'root', group: 'root', size: 220,
                                            content: "auto lo\niface lo inet loopback\n\nauto eth0\niface eth0 inet static\n  address 192.168.1.100\n  netmask 255.255.255.0\n  gateway 192.168.1.1\n  dns-nameservers 8.8.8.8 8.8.4.4\n"
                                        }
                                    }
                                },
                                'apache2': {
                                    type: 'dir', name: 'apache2', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'apache2.conf': {
                                            type: 'file', name: 'apache2.conf', permissions: '0644', owner: 'root', group: 'root', size: 1400,
                                            content: "DefaultRuntimeDir ${APACHE_RUN_DIR}\nPidFile ${APACHE_PID_FILE}\nTimeout 300\nKeepAlive On\nMaxKeepAliveRequests 100\nKeepAliveTimeout 5\nIncludeOptional sites-enabled/*.conf\n"
                                        },
                                        'sites-available': {
                                            type: 'dir', name: 'sites-available', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                            children: {
                                                '000-default.conf': {
                                                    type: 'file', name: '000-default.conf', permissions: '0644', owner: 'root', group: 'root', size: 450,
                                                    content: "<VirtualHost *:80>\n  ServerAdmin webmaster@localhost\n  DocumentRoot /var/www/html\n  ErrorLog ${APACHE_LOG_DIR}/error.log\n  CustomLog ${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>\n"
                                                },
                                                'zsem.conf': {
                                                    type: 'file', name: 'zsem.conf', permissions: '0644', owner: 'root', group: 'root', size: 520,
                                                    content: "<VirtualHost *:80>\n  ServerName www.zsem.local\n  ServerAlias zsem.local\n  DocumentRoot /var/www/zsem\n  <Directory /var/www/zsem>\n    AllowOverride All\n    Require all granted\n  </Directory>\n</VirtualHost>\n"
                                                }
                                            }
                                        },
                                        'sites-enabled': {
                                            type: 'dir', name: 'sites-enabled', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                            children: {
                                                '000-default.conf': { type: 'symlink', name: '000-default.conf', target: '../sites-available/000-default.conf', permissions: '0777', owner: 'root', group: 'root', size: 35 }
                                            }
                                        }
                                    }
                                },
                                'bind': {
                                    type: 'dir', name: 'bind', permissions: '0755', owner: 'root', group: 'bind', created: now, modified: now,
                                    children: {
                                        'named.conf': {
                                            type: 'file', name: 'named.conf', permissions: '0644', owner: 'root', group: 'bind', size: 350,
                                            content: "include \"/etc/bind/named.conf.options\";\ninclude \"/etc/bind/named.conf.local\";\ninclude \"/etc/bind/named.conf.default-zones\";\n"
                                        },
                                        'named.conf.local': {
                                            type: 'file', name: 'named.conf.local', permissions: '0644', owner: 'root', group: 'bind', size: 280,
                                            content: "zone \"zsem.local\" {\n    type master;\n    file \"/etc/bind/db.zsem.local\";\n};\n"
                                        },
                                        'db.zsem.local': {
                                            type: 'file', name: 'db.zsem.local', permissions: '0644', owner: 'root', group: 'bind', size: 620,
                                            content: "$TTL 604800\n@ IN SOA ns1.zsem.local. admin.zsem.local. (\n  2 ; Serial\n  604800 ; Refresh\n  86400 ; Retry\n  2419200 ; Expire\n  604800 ) ; Negative Cache TTL\n;\n@       IN  NS      ns1.zsem.local.\nns1     IN  A       192.168.1.100\n@       IN  A       192.168.1.100\nwww     IN  CNAME   zsem.local.\nmail    IN  A       192.168.1.100\n@       IN  MX  10  mail.zsem.local.\n"
                                        }
                                    }
                                },
                                'samba': {
                                    type: 'dir', name: 'samba', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'smb.conf': {
                                            type: 'file', name: 'smb.conf', permissions: '0644', owner: 'root', group: 'root', size: 850,
                                            content: "[global]\n   workgroup = WORKGROUP\n   server string = ZSEM Samba Server\n   security = user\n   map to guest = bad user\n\n[egzamin]\n   path = /srv/samba/egzamin\n   read only = no\n   browsable = yes\n   valid users = student, @admin\n   create mask = 0775\n   directory mask = 0775\n"
                                        }
                                    }
                                },
                                'dhcp': {
                                    type: 'dir', name: 'dhcp', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'dhcpd.conf': {
                                            type: 'file', name: 'dhcpd.conf', permissions: '0644', owner: 'root', group: 'root', size: 480,
                                            content: "default-lease-time 600;\nmax-lease-time 7200;\nauthoritative;\n\nsubnet 192.168.1.0 netmask 255.255.255.0 {\n  range 192.168.1.150 192.168.1.200;\n  option routers 192.168.1.1;\n  option domain-name-servers 8.8.8.8, 8.8.4.4;\n  option domain-name \"zsem.local\";\n}\n"
                                        }
                                    }
                                },
                                'ssh': {
                                    type: 'dir', name: 'ssh', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'sshd_config': {
                                            type: 'file', name: 'sshd_config', permissions: '0600', owner: 'root', group: 'root', size: 640,
                                            content: "Port 22\nPermitRootLogin prohibit-password\nPasswordAuthentication yes\nPubkeyAuthentication yes\nX11Forwarding no\nMaxAuthTries 3\n"
                                        }
                                    }
                                },
                                'ssl': {
                                    type: 'dir', name: 'ssl', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'certs': { type: 'dir', name: 'certs', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now, children: {} },
                                        'private': { type: 'dir', name: 'private', permissions: '0700', owner: 'root', group: 'root', created: now, modified: now, children: {} }
                                    }
                                },
                                'exports': {
                                    type: 'file', name: 'exports', permissions: '0644', owner: 'root', group: 'root', size: 210,
                                    content: "/srv/nfs/dane  192.168.1.0/24(rw,sync,no_subtree_check,no_root_squash)\n"
                                },
                                'vsftpd.conf': {
                                    type: 'file', name: 'vsftpd.conf', permissions: '0644', owner: 'root', group: 'root', size: 310,
                                    content: "listen=YES\nanonymous_enable=NO\nlocal_enable=YES\nwrite_enable=YES\nlocal_umask=022\nchroot_local_user=YES\n"
                                }
                            }
                        },
                        'home': {
                            type: 'dir', name: 'home', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'student': {
                                    type: 'dir', name: 'student', permissions: '0750', owner: 'student', group: 'student', created: now, modified: now,
                                    children: {
                                        'Desktop': { type: 'dir', name: 'Desktop', permissions: '0755', owner: 'student', group: 'student', created: now, modified: now, children: {} },
                                        'Documents': { type: 'dir', name: 'Documents', permissions: '0755', owner: 'student', group: 'student', created: now, modified: now, children: {} },
                                        'Downloads': { type: 'dir', name: 'Downloads', permissions: '0755', owner: 'student', group: 'student', created: now, modified: now, children: {} },
                                        'projekty': {
                                            type: 'dir', name: 'projekty', permissions: '0755', owner: 'student', group: 'student', created: now, modified: now,
                                            children: {
                                                'test.py': { type: 'file', name: 'test.py', permissions: '0644', owner: 'student', group: 'student', size: 120, content: "#!/usr/bin/env python3\nprint('Test skryptu python w ZSEM CLI Lab')\n" }
                                            }
                                        },
                                        'script.sh': {
                                            type: 'file', name: 'script.sh', permissions: '0644', owner: 'student', group: 'student', size: 160,
                                            content: "#!/bin/bash\necho \"Rozpoczynam diagnostykę sieci...\"\nping -c 2 192.168.1.1\necho \"Zakończono pomyślnie.\"\n"
                                        },
                                        '.bashrc': {
                                            type: 'file', name: '.bashrc', permissions: '0644', owner: 'student', group: 'student', size: 3200,
                                            content: "# ~/.bashrc\nalias ll='ls -la'\nalias la='ls -A'\nalias l='ls -CF'\nexport PATH=$PATH:/usr/local/bin\n"
                                        },
                                        '.profile': {
                                            type: 'file', name: '.profile', permissions: '0644', owner: 'student', group: 'student', size: 800,
                                            content: "# ~/.profile\nexport EDITOR=nano\n"
                                        }
                                    }
                                }
                            }
                        },
                        'srv': {
                            type: 'dir', name: 'srv', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'samba': {
                                    type: 'dir', name: 'samba', permissions: '0777', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'egzamin': { type: 'dir', name: 'egzamin', permissions: '0777', owner: 'student', group: 'student', created: now, modified: now, children: {} }
                                    }
                                },
                                'ftp': { type: 'dir', name: 'ftp', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now, children: {} },
                                'nfs': {
                                    type: 'dir', name: 'nfs', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'dane': { type: 'dir', name: 'dane', permissions: '0777', owner: 'student', group: 'student', created: now, modified: now, children: {} }
                                    }
                                }
                            }
                        },
                        'var': {
                            type: 'dir', name: 'var', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'log': {
                                    type: 'dir', name: 'log', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'syslog': {
                                            type: 'file', name: 'syslog', permissions: '0640', owner: 'syslog', group: 'adm', size: 4520,
                                            content: "Aug 17 08:00:01 zsem-lab systemd[1]: Started Daily apt upgrade and clean activities.\nAug 17 08:15:22 zsem-lab kernel: [ 12.345678] eth0: Link is Up - 1000Mbps/Full\nAug 17 08:15:23 zsem-lab dhclient[742]: bound to 192.168.1.100 -- renewal in 43200 seconds.\nAug 17 08:30:10 zsem-lab sshd[1240]: Server listening on 0.0.0.0 port 22.\nAug 17 08:45:00 zsem-lab apache2[1420]: AH00558: apache2: Could not reliably determine the server's fully qualified domain name\nAug 17 09:00:00 zsem-lab CRON[2100]: (root) CMD (/usr/bin/check_backup.sh)\n"
                                        },
                                        'auth.log': {
                                            type: 'file', name: 'auth.log', permissions: '0640', owner: 'syslog', group: 'adm', size: 1200,
                                            content: "Aug 17 08:10:00 zsem-lab systemd-logind[650]: New session 1 of user student.\nAug 17 08:10:01 zsem-lab sudo: student : TTY=pts/0 ; PWD=/home/student ; USER=root ; COMMAND=/bin/cat /etc/shadow\n"
                                        }
                                    }
                                },
                                'www': {
                                    type: 'dir', name: 'www', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'html': {
                                            type: 'dir', name: 'html', permissions: '0755', owner: 'www-data', group: 'www-data', created: now, modified: now,
                                            children: {
                                                'index.html': {
                                                    type: 'file', name: 'index.html', permissions: '0644', owner: 'www-data', group: 'www-data', size: 420,
                                                    content: "<!DOCTYPE html>\n<html>\n<head><title>ZSEM Web Server</title></head>\n<body>\n<h1>Serwer Apache2/Nginx działa poprawnie!</h1>\n<p>Egzamin CKE INF.02 / INF.03</p>\n</body>\n</html>\n"
                                                }
                                            }
                                        },
                                        'zsem': {
                                            type: 'dir', name: 'zsem', permissions: '0755', owner: 'www-data', group: 'www-data', created: now, modified: now,
                                            children: {
                                                'index.html': {
                                                    type: 'file', name: 'index.html', permissions: '0644', owner: 'www-data', group: 'www-data', size: 280,
                                                    content: "<!DOCTYPE html>\n<html><body><h1>Witaj na www.zsem.local</h1></body></html>\n"
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        'tmp': { type: 'dir', name: 'tmp', permissions: '1777', owner: 'root', group: 'root', created: now, modified: now, children: {} }
                    }
                },
                windows: {
                    type: 'dir',
                    name: 'C:',
                    created: now,
                    modified: now,
                    children: {
                        'Windows': {
                            type: 'dir', name: 'Windows', created: now, modified: now,
                            children: {
                                'System32': {
                                    type: 'dir', name: 'System32', created: now, modified: now,
                                    children: {
                                        'cmd.exe': { type: 'file', name: 'cmd.exe', size: 289280 },
                                        'ipconfig.exe': { type: 'file', name: 'ipconfig.exe', size: 45056 },
                                        'ping.exe': { type: 'file', name: 'ping.exe', size: 24576 },
                                        'net.exe': { type: 'file', name: 'net.exe', size: 58880 },
                                        'netsh.exe': { type: 'file', name: 'netsh.exe', size: 104448 },
                                        'diskpart.exe': { type: 'file', name: 'diskpart.exe', size: 231424 }
                                    }
                                }
                            }
                        },
                        'Users': {
                            type: 'dir', name: 'Users', created: now, modified: now,
                            children: {
                                'Student': {
                                    type: 'dir', name: 'Student', created: now, modified: now,
                                    children: {
                                        'Desktop': { type: 'dir', name: 'Desktop', created: now, modified: now, children: {} },
                                        'Documents': {
                                            type: 'dir', name: 'Documents', created: now, modified: now,
                                            children: {
                                                'raport_sieci.txt': { type: 'file', name: 'raport_sieci.txt', size: 140, content: "Raport konfiguracji interfejsu sieciowego ZSEM\nData: 17.08.2026\nStatus: OK\n" }
                                            }
                                        },
                                        'Downloads': { type: 'dir', name: 'Downloads', created: now, modified: now, children: {} },
                                        'script.bat': {
                                            type: 'file', name: 'script.bat', size: 110,
                                            content: "@echo off\r\necho Rozpoczynam test sieci Windows...\r\nping 127.0.0.1 -n 2\r\npause\r\n"
                                        }
                                    }
                                },
                                'Administrator': { type: 'dir', name: 'Administrator', created: now, modified: now, children: {} }
                            }
                        },
                        'inetpub': {
                            type: 'dir', name: 'inetpub', created: now, modified: now,
                            children: {
                                'wwwroot': {
                                    type: 'dir', name: 'wwwroot', created: now, modified: now,
                                    children: {
                                        'index.html': { type: 'file', name: 'index.html', size: 340, content: "<!DOCTYPE html>\r\n<html><head><title>IIS Windows Server</title></head><body><h1>Internet Information Services (IIS) Dziala</h1></body></html>\r\n" }
                                    }
                                }
                            }
                        },
                        'Dane': {
                            type: 'dir', name: 'Dane', created: now, modified: now,
                            children: {
                                'projekty.txt': { type: 'file', name: 'projekty.txt', size: 80, content: "Dane projektowe dla grupy egzaminacyjnej.\r\n" }
                            }
                        }
                    }
                }
            };
        }

        save() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.root));
            } catch (e) {
                console.warn('VFS save failed (quota exceeded):', e);
            }
        }

        load() {
            try {
                const data = localStorage.getItem(this.storageKey);
                return data ? JSON.parse(data) : null;
            } catch (e) {
                return null;
            }
        }

        reset() {
            this.root = this.defaultFileSystem();
            this.currentDirLinux = '/home/student';
            this.currentDirWin = 'C:\\Users\\Student';
            this.save();
        }

        normalizePath(pathStr, isWin = false) {
            if (!pathStr || pathStr.trim() === '') {
                return isWin ? this.currentDirWin : this.currentDirLinux;
            }
            let p = pathStr.trim();
            if (isWin) {
                p = p.replace(/\//g, '\\');
                if (p === '.') return this.currentDirWin;
                if (p === '..') {
                    const segs = this.currentDirWin.split('\\').filter(Boolean);
                    if (segs.length > 1) segs.pop();
                    return segs.join('\\') || 'C:';
                }
                if (!p.includes(':')) {
                    if (p.startsWith('\\')) return 'C:' + p;
                    return (this.currentDirWin.endsWith('\\') ? this.currentDirWin : this.currentDirWin + '\\') + p;
                }
                return p;
            } else {
                p = p.replace(/\\/g, '/');
                if (p.startsWith('~')) p = '/home/student' + p.substring(1);
                if (p === '.') return this.currentDirLinux;
                if (p === '..') {
                    if (this.currentDirLinux === '/') return '/';
                    const segs = this.currentDirLinux.split('/').filter(Boolean);
                    segs.pop();
                    return '/' + segs.join('/');
                }
                if (!p.startsWith('/')) {
                    const base = this.currentDirLinux === '/' ? '' : this.currentDirLinux;
                    return base + '/' + p;
                }
                const parts = p.split('/').filter(Boolean);
                const resolved = [];
                for (const part of parts) {
                    if (part === '.') continue;
                    if (part === '..') { if (resolved.length) resolved.pop(); }
                    else resolved.push(part);
                }
                return '/' + resolved.join('/');
            }
        }

        getNode(pathStr, isWin = false) {
            const norm = this.normalizePath(pathStr, isWin);
            if (isWin) {
                const parts = norm.split('\\').filter(Boolean);
                let curr = this.root.windows;
                if (parts[0] && parts[0].toUpperCase().startsWith('C:')) parts.shift();
                for (const part of parts) {
                    if (!curr || curr.type !== 'dir' || !curr.children) return null;
                    const matchKey = Object.keys(curr.children).find(k => k.toLowerCase() === part.toLowerCase());
                    if (!matchKey) return null;
                    curr = curr.children[matchKey];
                }
                return curr;
            } else {
                if (norm === '/') return this.root.linux;
                const parts = norm.split('/').filter(Boolean);
                let curr = this.root.linux;
                for (const part of parts) {
                    if (!curr || curr.type !== 'dir' || !curr.children || !curr.children[part]) return null;
                    curr = curr.children[part];
                }
                return curr;
            }
        }

        createFile(pathStr, content = '', isWin = false) {
            const norm = this.normalizePath(pathStr, isWin);
            const sep = isWin ? '\\' : '/';
            const parts = norm.split(sep).filter(Boolean);
            const fileName = parts.pop();
            const parentPath = (isWin ? (parts[0]?.includes(':') ? '' : 'C:\\') : '/') + parts.join(sep);
            const parent = this.getNode(parentPath || (isWin ? 'C:\\' : '/'), isWin);

            if (!parent || parent.type !== 'dir') return false;
            parent.children = parent.children || {};
            parent.children[fileName] = {
                type: 'file',
                name: fileName,
                permissions: isWin ? '0666' : '0644',
                owner: isWin ? 'Student' : 'student',
                group: isWin ? 'Users' : 'student',
                size: content.length,
                content: content,
                modified: new Date().toISOString()
            };
            this.save();
            return true;
        }

        createDirectory(pathStr, recursive = false, isWin = false) {
            const norm = this.normalizePath(pathStr, isWin);
            const sep = isWin ? '\\' : '/';
            const parts = norm.split(sep).filter(Boolean);
            if (isWin && parts[0]?.toUpperCase().startsWith('C:')) parts.shift();

            let curr = isWin ? this.root.windows : this.root.linux;
            for (let i = 0; i < parts.length; i++) {
                const part = parts[i];
                curr.children = curr.children || {};
                const matchKey = isWin ? Object.keys(curr.children).find(k => k.toLowerCase() === part.toLowerCase()) : part;
                if (!matchKey || !curr.children[matchKey]) {
                    if (!recursive && i < parts.length - 1) return false;
                    curr.children[part] = {
                        type: 'dir',
                        name: part,
                        permissions: isWin ? '0777' : '0755',
                        owner: isWin ? 'Student' : 'student',
                        group: isWin ? 'Users' : 'student',
                        created: new Date().toISOString(),
                        modified: new Date().toISOString(),
                        children: {}
                    };
                    curr = curr.children[part];
                } else {
                    curr = curr.children[matchKey];
                    if (curr.type !== 'dir') return false;
                }
            }
            this.save();
            return true;
        }

        removeNode(pathStr, recursive = false, isWin = false) {
            const norm = this.normalizePath(pathStr, isWin);
            const sep = isWin ? '\\' : '/';
            const parts = norm.split(sep).filter(Boolean);
            const targetName = parts.pop();
            const parentPath = (isWin ? (parts[0]?.includes(':') ? '' : 'C:\\') : '/') + parts.join(sep);
            const parent = this.getNode(parentPath || (isWin ? 'C:\\' : '/'), isWin);

            if (!parent || parent.type !== 'dir' || !parent.children) return false;
            const matchKey = isWin ? Object.keys(parent.children).find(k => k.toLowerCase() === targetName.toLowerCase()) : targetName;
            if (!matchKey || !parent.children[matchKey]) return false;

            const target = parent.children[matchKey];
            if (target.type === 'dir' && Object.keys(target.children || {}).length > 0 && !recursive) {
                return false;
            }
            delete parent.children[matchKey];
            this.save();
            return true;
        }

        copyNode(srcPath, dstPath, recursive = false, isWin = false) {
            const src = this.getNode(srcPath, isWin);
            if (!src) return false;
            const normDst = this.normalizePath(dstPath, isWin);
            const dstNode = this.getNode(normDst, isWin);

            let targetParentPath = normDst;
            let targetName = src.name;
            if (!dstNode || dstNode.type !== 'dir') {
                const sep = isWin ? '\\' : '/';
                const parts = normDst.split(sep).filter(Boolean);
                targetName = parts.pop();
                targetParentPath = (isWin ? '' : '/') + parts.join(sep);
            }

            const parent = this.getNode(targetParentPath || (isWin ? 'C:\\' : '/'), isWin);
            if (!parent || parent.type !== 'dir') return false;

            parent.children = parent.children || {};
            parent.children[targetName] = JSON.parse(JSON.stringify(src));
            parent.children[targetName].name = targetName;
            parent.children[targetName].modified = new Date().toISOString();
            this.save();
            return true;
        }

        moveNode(srcPath, dstPath, isWin = false) {
            const ok = this.copyNode(srcPath, dstPath, true, isWin);
            if (ok) return this.removeNode(srcPath, true, isWin);
            return false;
        }

        listDirectory(pathStr, isWin = false) {
            const node = this.getNode(pathStr, isWin);
            if (!node || node.type !== 'dir') return null;
            return Object.values(node.children || {});
        }

        generateTreeAscii(node, prefix = '') {
            if (!node || node.type !== 'dir' || !node.children) return '';
            const entries = Object.values(node.children);
            let out = '';
            entries.forEach((child, idx) => {
                const isLast = idx === entries.length - 1;
                const pointer = isLast ? '└── ' : '├── ';
                out += `${prefix}${pointer}${child.name}${child.type === 'dir' ? '/' : ''}\n`;
                if (child.type === 'dir') {
                    out += this.generateTreeAscii(child, prefix + (isLast ? '    ' : '│   '));
                }
            });
            return out;
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 2. NETWORK & SYSTEM STATE STORE
    // ════════════════════════════════════════════════════════════════════════════

    class NetworkState {
        constructor() {
            this.storageKey = 'zsem_netstate_v2';
            this.state = this.load() || this.defaultState();
        }

        defaultState() {
            return {
                ip: '192.168.1.100',
                netmask: '255.255.255.0',
                cidr: '24',
                gateway: '192.168.1.1',
                dns: ['192.168.1.1', '8.8.8.8'],
                mac: '00:50:56:C0:00:08',
                hostname: 'zsem-lab',
                winHostname: 'ZSEM-STUDENT',
                currentUserLinux: 'student',
                currentUserWin: 'student',
                dhcp: true,
                services: {
                    'apache2': { name: 'Apache HTTP Server', status: 'RUNNING', port: 80, enabled: true },
                    'bind9': { name: 'BIND9 DNS Server', status: 'RUNNING', port: 53, enabled: true },
                    'smbd': { name: 'Samba SMB/CIFS Daemon', status: 'RUNNING', port: 445, enabled: true },
                    'nmbd': { name: 'Samba NetBIOS Daemon', status: 'RUNNING', port: 137, enabled: true },
                    'isc-dhcp-server': { name: 'ISC DHCP Server', status: 'STOPPED', port: 67, enabled: false },
                    'vsftpd': { name: 'vsftpd FTP Server', status: 'STOPPED', port: 21, enabled: false },
                    'postfix': { name: 'Postfix Mail Transport Agent', status: 'RUNNING', port: 25, enabled: true },
                    'nfs-kernel-server': { name: 'NFS Kernel Server', status: 'STOPPED', port: 2049, enabled: false },
                    'ssh': { name: 'OpenSSH Daemon', status: 'RUNNING', port: 22, enabled: true },
                    'mysql': { name: 'MySQL Community Server', status: 'RUNNING', port: 3306, enabled: true },
                    'fail2ban': { name: 'Fail2ban Intrusion Prevention', status: 'STOPPED', port: 0, enabled: false },
                    'w3svc': { name: 'World Wide Web Publishing Service (IIS)', status: 'RUNNING', port: 80, enabled: true }
                },
                installedPackages: ['bash', 'coreutils', 'net-tools', 'iproute2', 'apache2', 'bind9', 'samba', 'postfix', 'mysql-server', 'openssh-server', 'nano', 'curl', 'wget', 'nmap', 'tar', 'gzip'],
                firewallLinux: {
                    ufwEnabled: false,
                    rules: [
                        { port: 22, proto: 'tcp', action: 'ALLOW' },
                        { port: 80, proto: 'tcp', action: 'ALLOW' }
                    ],
                    iptablesInput: [
                        { chain: 'INPUT', proto: 'all', dport: 0, target: 'ACCEPT' }
                    ]
                },
                firewallWin: {
                    enabled: true,
                    rules: [
                        { name: 'AllowHTTP', port: 80, proto: 'TCP', dir: 'In', action: 'Allow' },
                        { name: 'AllowRDP', port: 3389, proto: 'TCP', dir: 'In', action: 'Allow' }
                    ]
                },
                stats: {
                    commandsExecuted: 0,
                    completedScenarios: 0,
                    packagesInstalled: 14,
                    sessionMinutes: 0
                }
            };
        }

        save() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.state));
            } catch (e) {
                console.warn('NetState save failed:', e);
            }
        }

        load() {
            try {
                const d = localStorage.getItem(this.storageKey);
                return d ? JSON.parse(d) : null;
            } catch (e) {
                return null;
            }
        }

        reset() {
            this.state = this.defaultState();
            this.save();
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 3. INLINE SUB-SHELLS (POWERSHELL, DISKPART, MYSQL, NSLOOKUP, PYTHON, SSH)
    // ════════════════════════════════════════════════════════════════════════════

    class PowerShellEngine {
        constructor(term) {
            this.term = term;
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();
            const args = this.term.parseArgs(raw);
            const cmdlet = args[0]?.toLowerCase();

            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: '' };
            if (lower === 'cls' || lower === 'clear-host') return { action: 'clear', output: '' };

            if (cmdlet === 'get-childitem' || cmdlet === 'dir' || cmdlet === 'gci' || cmdlet === 'ls') {
                const target = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || this.term.vfs.currentDirWin;
                const items = this.term.vfs.listDirectory(target, true) || [];
                let out = `\r\n    Directory: ${this.term.vfs.normalizePath(target, true)}\r\n\r\nMode                 LastWriteTime         Length Name\r\n----                 -------------         ------ ----\r\n`;
                out += items.map(i => {
                    const mode = i.type === 'dir' ? 'd-----' : '-a----';
                    return `${mode}        17.08.2026     08:30       ${String(i.size || 0).padStart(8)} ${i.name}`;
                }).join('\r\n');
                return { output: out + '\r\n' };
            }

            if (cmdlet === 'get-service' || cmdlet === 'gsv') {
                const nameFilter = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet)?.toLowerCase();
                const svcs = this.term.net.state.services;
                let out = `\r\nStatus   Name               DisplayName\r\n------   ----               -----------\r\n`;
                out += Object.entries(svcs)
                    .filter(([k]) => !nameFilter || k.toLowerCase().includes(nameFilter))
                    .map(([k, v]) => {
                        const st = v.status.includes('RUNNING') || v.status === 'RUNNING' ? 'Running' : 'Stopped';
                        return `${st.padEnd(8)} ${k.padEnd(18)} ${v.name}`;
                    }).join('\r\n');
                return { output: out + '\r\n' };
            }

            if (cmdlet === 'start-service' || cmdlet === 'sasv') {
                const name = (args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'w3svc').toLowerCase();
                if (this.term.net.state.services[name]) {
                    this.term.net.state.services[name].status = 'RUNNING';
                    this.term.net.save();
                }
                return { output: '' };
            }

            if (cmdlet === 'stop-service' || cmdlet === 'spsv') {
                const name = (args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'w3svc').toLowerCase();
                if (this.term.net.state.services[name]) {
                    this.term.net.state.services[name].status = 'STOPPED';
                    this.term.net.save();
                }
                return { output: '' };
            }

            if (cmdlet === 'restart-service') {
                const name = (args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'w3svc').toLowerCase();
                if (this.term.net.state.services[name]) {
                    this.term.net.state.services[name].status = 'RUNNING';
                    this.term.net.save();
                }
                return { output: `WARNING: Waiting for service '${name}' to finish restarting...\r\n` };
            }

            if (cmdlet === 'get-netipaddress') {
                const net = this.term.net.state;
                return {
                    output: `\r\nIPAddress         : ${net.ip}\r\nInterfaceIndex    : 4\r\nInterfaceAlias    : Ethernet\r\nAddressFamily     : IPv4\r\nType              : Unicast\r\nPrefixLength      : ${net.cidr || '24'}\r\nPrefixOrigin      : ${net.dhcp ? 'Dhcp' : 'Manual'}\r\nAddressState      : Preferred\r\n`
                };
            }

            if (cmdlet === 'new-netipaddress') {
                const ipIdx = args.indexOf('-IPAddress');
                const ip = ipIdx !== -1 ? args[ipIdx + 1] : args[1];
                const prefixIdx = args.indexOf('-PrefixLength');
                const prefix = prefixIdx !== -1 ? args[prefixIdx + 1] : (args.indexOf('-DefaultGateway') !== -1 ? '24' : '24');
                const gwIdx = args.indexOf('-DefaultGateway');
                if (gwIdx !== -1 && args[gwIdx + 1]) this.term.net.state.gateway = args[gwIdx + 1];
                if (ip) {
                    this.term.net.state.ip = ip;
                    this.term.net.state.cidr = prefix;
                    this.term.net.state.dhcp = false;
                    this.term.net.save();
                    return { output: `\r\nIPAddress         : ${ip}\r\nInterfaceAlias    : Ethernet\r\nAddressState      : Preferred\r\n` };
                }
                return { output: 'New-NetIPAddress: Missing -IPAddress parameter.\r\n' };
            }

            if (cmdlet === 'get-netipconfiguration' || cmdlet === 'gip') {
                const net = this.term.net.state;
                return {
                    output: `\r\nInterfaceAlias       : Ethernet\r\nInterfaceIndex       : 4\r\nIPv4Address          : ${net.ip}\r\nIPv4DefaultGateway   : ${net.gateway}\r\nDNSServer            : ${net.dns.join(', ')}\r\n`
                };
            }

            if (cmdlet === 'get-netroute') {
                const net = this.term.net.state;
                return {
                    output: `\r\nifIndex DestinationPrefix       NextHop          RouteMetric ifMetric PolicyStore\r\n------- -----------------       -------          ----------- -------- -----------\r\n4       0.0.0.0/0               ${net.gateway.padEnd(16)} 25          0        ActiveStore\r\n4       192.168.1.0/24          0.0.0.0          281         0        ActiveStore\r\n`
                };
            }

            if (cmdlet === 'new-netroute') {
                const destIdx = args.indexOf('-DestinationPrefix');
                const nextIdx = args.indexOf('-NextHop');
                const dest = destIdx !== -1 ? args[destIdx + 1] : '10.0.0.0/24';
                const next = nextIdx !== -1 ? args[nextIdx + 1] : '192.168.1.1';
                return { output: `\r\nifIndex DestinationPrefix       NextHop          RouteMetric\r\n------- -----------------       -------          -----------\r\n4       ${dest.padEnd(23)} ${next.padEnd(16)} 25\r\n` };
            }

            if (cmdlet === 'get-dnsclientserveraddress') {
                const net = this.term.net.state;
                return {
                    output: `\r\nInterfaceAlias               Interface Address ServerAddresses\r\n                             Index     Family\r\n--------------               --------- ------- ---------------\r\nEthernet                             4 IPv4    {${net.dns.join(', ')}}\r\n`
                };
            }

            if (cmdlet === 'set-dnsclientserveraddress') {
                const srvIdx = args.indexOf('-ServerAddresses');
                if (srvIdx !== -1 && args[srvIdx + 1]) {
                    this.term.net.state.dns = args[srvIdx + 1].replace(/[()@'"]/g, '').split(',');
                    this.term.net.save();
                }
                return { output: '' };
            }

            if (cmdlet === 'get-netadapter') {
                const net = this.term.net.state;
                return {
                    output: `\r\nName                      InterfaceDescription                    ifIndex Status       MacAddress             LinkSpeed\r\n----                      --------------------                    ------- ------       ----------             ---------\r\nEthernet                  Intel(R) PRO/1000 MT Network Connection       4 Up           ${net.mac.replace(/:/g, '-')}            1 Gbps\r\n`
                };
            }

            if (cmdlet === 'test-netconnection' || cmdlet === 'tnc') {
                const hostIdx = args.indexOf('-ComputerName');
                const portIdx = args.indexOf('-Port');
                const host = hostIdx !== -1 ? args[hostIdx + 1] : args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || '8.8.8.8';
                const port = portIdx !== -1 ? args[portIdx + 1] : '80';
                return {
                    output: `\r\nComputerName           : ${host}\r\nRemoteAddress          : ${host}\r\nRemotePort             : ${port}\r\nInterfaceAlias         : Ethernet\r\nSourceAddress          : ${this.term.net.state.ip}\r\nTcpTestSucceeded       : True\r\nPingSucceeded          : True\r\nPingReplyDetails (RTT) : 12 ms\r\n`
                };
            }

            if (cmdlet === 'resolve-dnsname') {
                const nameIdx = args.indexOf('-Name');
                const name = nameIdx !== -1 ? args[nameIdx + 1] : args[1] || 'google.pl';
                let ip = '142.250.187.195';
                if (name.includes('zsem.local')) ip = '192.168.1.100';
                return {
                    output: `\r\nName                               Type   TTL   Section    IPAddress\r\n----                               ----   ---   -------    ---------\r\n${name.padEnd(34)} A      300   Answer     ${ip}\r\n`
                };
            }

            if (cmdlet === 'get-netfirewallrule') {
                return {
                    output: `\r\nName                  : AllowHTTP\r\nDisplayName           : Allow HTTP Inbound Port 80\r\nDescription           : Inbound rule for HTTP\r\nDisplayGroup          : Web Server (IIS)\r\nGroup                 : @{Microsoft.Windows.Server.Web}\r\nEnabled               : True\r\nProfile               : Any\r\nPlatform              : {}\r\nDirection             : Inbound\r\nAction                : Allow\r\n`
                };
            }

            if (cmdlet === 'new-netfirewallrule') {
                const nameIdx = args.indexOf('-DisplayName') !== -1 ? args.indexOf('-DisplayName') : args.indexOf('-Name');
                const ruleName = nameIdx !== -1 ? args[nameIdx + 1] : 'NewRule';
                return { output: `\r\nName                  : ${ruleName}\r\nDisplayName           : ${ruleName}\r\nEnabled               : True\r\nDirection             : Inbound\r\nAction                : Allow\r\n` };
            }

            if (cmdlet === 'get-disk') {
                return {
                    output: `\r\nNumber Friendly Name Serial Number                    HealthStatus         OperationalStatus      Total Size Partition\r\n                                                                                                                     Style\r\n------ ------------- -------------                    ------------         -----------------      ---------- ---------\r\n0      VBOX HARDDISK VB892-001                        Healthy              Online                     238 GB GPT\r\n1      VBOX HARDDISK VB892-002                        Healthy              Online                      64 GB MBR\r\n`
                };
            }

            if (cmdlet === 'initialize-disk') {
                return { output: '' };
            }

            if (cmdlet === 'new-partition') {
                return {
                    output: `\r\n   DiskPath: \\\\?\\scsi#disk&ven_vbox&prod_harddisk#4&12ac34&0&000000#{53f56307-b6bf-11d0-94f2-00a0c91efb8b}\r\n\r\nPartitionNumber  DriveLetter Offset                                                    Size Type\r\n---------------  ----------- ------                                                    ---- ----\r\n1                E           1048576                                                  64 GB Basic\r\n`
                };
            }

            if (cmdlet === 'format-volume') {
                return {
                    output: `\r\nDriveLetter FriendlyName FileSystemType DriveType HealthStatus OperationalStatus SizeRemaining     Size\r\n----------- ------------ -------------- --------- ------------ ----------------- -------------     ----\r\nE           Dane         NTFS           Fixed     Healthy      OK                     63.89 GB    64 GB\r\n`
                };
            }

            if (cmdlet === 'get-partition') {
                return {
                    output: `\r\n   DiskPath: \\\\?\\scsi#disk&ven_vbox&prod_harddisk#4&12ac34&0&000000#{53f56307-b6bf-11d0-94f2-00a0c91efb8b}\r\n\r\nPartitionNumber  DriveLetter Offset                                                    Size Type\r\n---------------  ----------- ------                                                    ---- ----\r\n1                C           1048576                                                 237 GB Basic\r\n`
                };
            }

            if (cmdlet === 'get-volume') {
                return {
                    output: `\r\nDriveLetter FriendlyName FileSystemType DriveType HealthStatus OperationalStatus SizeRemaining     Size\r\n----------- ------------ -------------- --------- ------------ ----------------- -------------     ----\r\nC           Windows      NTFS           Fixed     Healthy      OK                    180.45 GB   237 GB\r\nE           Dane         NTFS           Fixed     Healthy      OK                     63.89 GB    64 GB\r\n`
                };
            }

            if (cmdlet === 'get-windowsfeature') {
                return {
                    output: `\r\nDisplay Name                                            Name                       Install State\r\n------------                                            ----                       -------------\r\n[X] Web Server (IIS)                                    Web-Server                     Installed\r\n[ ] DNS Server                                          DNS                            Available\r\n[ ] DHCP Server                                         DHCP                           Available\r\n[X] Active Directory Domain Services                    AD-Domain-Services             Installed\r\n`
                };
            }

            if (cmdlet === 'install-windowsfeature') {
                const name = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'Web-Server';
                return {
                    output: `\r\nSuccess Restart Needed Exit Code      Feature Result\r\n------- -------------- ---------      --------------\r\nTrue    No             Success        {${name}}\r\n`
                };
            }

            if (cmdlet === 'get-localuser') {
                return {
                    output: `\r\nName               Enabled Description\r\n----               ------- -----------\r\nAdministrator      True    Built-in account for administering the computer/domain\r\nDefaultAccount     False   A user account managed by the system.\r\nGuest              False   Built-in account for guest access to the computer/domain\r\nStudent            True    Local Student Account\r\n`
                };
            }

            if (cmdlet === 'new-localuser') {
                const nameIdx = args.indexOf('-Name');
                const name = nameIdx !== -1 ? args[nameIdx + 1] : (args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'JanKowalski');
                return { output: `\r\nName               Enabled Description\r\n----               ------- -----------\r\n${name.padEnd(18)} True    \r\n` };
            }

            if (cmdlet === 'get-localgroup') {
                return {
                    output: `\r\nName               Description\r\n----               -----------\r\nAdministrators     Administrators have complete and unrestricted access to the computer/domain\r\nUsers              Users are prevented from making accidental or intentional system-wide changes\r\nRemote Desktop     Members in this group are granted the right to logon remotely\r\n`
                };
            }

            if (cmdlet === 'get-localgroupmember') {
                const grp = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'Administrators';
                return {
                    output: `\r\nObjectClass Name                          PrincipalSource\r\n----------- ----                          ---------------\r\nUser        ZSEM-STUDENT\\Administrator    Local\r\nUser        ZSEM-STUDENT\\Student          Local\r\n`
                };
            }

            if (cmdlet === 'add-localgroupmember') {
                return { output: '' };
            }

            if (cmdlet === 'get-process' || cmdlet === 'ps' || cmdlet === 'gps') {
                return {
                    output: `\r\nHandles  NPM(K)    PM(K)      WS(K)     CPU(s)     Id  SI ProcessName\r\n-------  ------    -----      -----     ------     --  -- -----------\r\n    342      18    15200      34120       1.24   1230   1 pwsh\r\n    512      32    84100     112450       4.85   2140   1 explorer\r\n    120       8     4120       8900       0.12   3410   1 svchost\r\n    200      14    22300      45000       0.95   1450   1 w3wp\r\n`
                };
            }

            if (cmdlet === 'stop-process' || cmdlet === 'spps' || cmdlet === 'kill') {
                return { output: '' };
            }

            if (cmdlet === 'get-content' || cmdlet === 'gc' || cmdlet === 'cat' || cmdlet === 'type') {
                const file = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'script.bat';
                const node = this.term.vfs.getNode(file, true);
                if (!node || node.type !== 'file') return { output: `Get-Content : Cannot find path '${file}' because it does not exist.\r\n` };
                return { output: (node.content || '') + '\r\n' };
            }

            if (cmdlet === 'set-content' || cmdlet === 'sc') {
                const file = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'test.txt';
                const valIdx = args.indexOf('-Value');
                const val = valIdx !== -1 ? args[valIdx + 1] : (args.filter(a => !a.startsWith('-'))[1] || '');
                this.term.vfs.createFile(file, val, true);
                return { output: '' };
            }

            if (cmdlet === 'add-content' || cmdlet === 'ac') {
                const file = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'test.txt';
                const valIdx = args.indexOf('-Value');
                const val = valIdx !== -1 ? args[valIdx + 1] : (args.filter(a => !a.startsWith('-'))[1] || '');
                const existing = this.term.vfs.getNode(file, true)?.content || '';
                this.term.vfs.createFile(file, existing + '\r\n' + val, true);
                return { output: '' };
            }

            if (cmdlet === 'new-item' || cmdlet === 'ni') {
                const name = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'NowyPlik.txt';
                const isDir = args.includes('-ItemType') && args[args.indexOf('-ItemType') + 1]?.toLowerCase() === 'directory';
                if (isDir) this.term.vfs.createDirectory(name, true, true);
                else this.term.vfs.createFile(name, '', true);
                return { output: `\r\n    Directory: ${this.term.vfs.currentDirWin}\r\n\r\nMode                 LastWriteTime         Length Name\r\n----                 -------------         ------ ----\r\n${isDir ? 'd-----' : '-a----'}        17.08.2026     08:30            0 ${name}\r\n` };
            }

            if (cmdlet === 'remove-item' || cmdlet === 'ri' || cmdlet === 'rm') {
                const name = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet);
                if (name) this.term.vfs.removeNode(name, true, true);
                return { output: '' };
            }

            if (cmdlet === 'copy-item' || cmdlet === 'cpi' || cmdlet === 'cp') {
                const clean = args.filter(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet);
                if (clean.length >= 2) this.term.vfs.copyNode(clean[0], clean[1], true, true);
                return { output: '' };
            }

            if (cmdlet === 'move-item' || cmdlet === 'mi' || cmdlet === 'mv') {
                const clean = args.filter(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet);
                if (clean.length >= 2) this.term.vfs.moveNode(clean[0], clean[1], true);
                return { output: '' };
            }

            if (cmdlet === 'write-host' || cmdlet === 'write-output' || cmdlet === 'echo') {
                const text = args.slice(1).filter(a => !a.startsWith('-')).join(' ');
                return { output: text + '\r\n' };
            }

            if (cmdlet === 'get-acl') {
                const path = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'C:\\Dane';
                return {
                    output: `\r\nPath   : Microsoft.PowerShell.Core\\FileSystem::${path}\r\nOwner  : BUILTIN\\Administrators\r\nGroup  : NT AUTHORITY\\SYSTEM\r\nAccess : BUILTIN\\Administrators Allow  FullControl\r\n         BUILTIN\\Users Allow  ReadAndExecute, Synchronize\r\n`
                };
            }

            if (cmdlet === 'select-object' || cmdlet === 'select' || cmdlet === 'where-object' || cmdlet === 'where' || cmdlet === 'sort-object' || cmdlet === 'sort') {
                return { output: 'PowerShell: Pipeline filter executed successfully.\r\n' };
            }

            if (cmdlet === 'get-help' || cmdlet === 'help' || cmdlet === 'man') {
                const subject = args.find(a => !a.startsWith('-') && a.toLowerCase() !== cmdlet) || 'Get-Service';
                return {
                    output: `\r\nNAME\r\n    ${subject}\r\n\r\nSYNOPSIS\r\n    Pobiera dane lub konfiguruje podsystem w środowisku Windows PowerShell 7.\r\n\r\nSYNTAX\r\n    ${subject} [[-Name] <String[]>] [-ComputerName <String[]>] [<CommonParameters>]\r\n`
                };
            }

            if (cmdlet === 'get-command' || cmdlet === 'gcm') {
                return {
                    output: `\r\nCommandType     Name                                               Version    Source\r\n-----------     ----                                               -------    ------\r\nCmdlet          Get-Service                                        3.1.0.0    Microsoft.PowerShell.Management\r\nCmdlet          Get-Process                                        3.1.0.0    Microsoft.PowerShell.Management\r\nCmdlet          Get-NetIPAddress                                   1.0.0.0    NetTCPIP\r\nCmdlet          Test-NetConnection                                 1.0.0.0    NetTCPIP\r\nCmdlet          Get-Disk                                           1.0.0.0    Storage\r\nCmdlet          Format-Volume                                      1.0.0.0    Storage\r\n`
                };
            }

            return { output: `PowerShell: '${cmd}' wykonano pomyślnie.\r\n` };
        }
    }

    class DiskpartShell {
        constructor() {
            this.selectedDisk = '0';
            this.selectedPart = null;
            this.selectedVol = '0';
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();
            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: 'Leaving DiskPart...\r\n' };
            if (lower === 'cls' || lower === 'clear') return { action: 'clear', output: '' };

            if (lower.startsWith('list disk') || lower === 'lis dis') {
                return {
                    output: `\r\n  Disk ###  Status         Size     Free     Dyn  Gpt\r\n  --------  -------------  -------  -------  ---  ---\r\n* Disk 0    Online          238 GB  1024 KB        *\r\n  Disk 1    Online           64 GB    64 GB\r\n`
                };
            }
            if (lower.startsWith('select disk') || lower.startsWith('sel dis')) {
                this.selectedDisk = lower.split(' ').pop() || '0';
                return { output: `\r\nDisk ${this.selectedDisk} is now the selected disk.\r\n` };
            }
            if (lower.startsWith('detail disk')) {
                return {
                    output: `\r\nVBOX HARDDISK\r\nDisk ID: {892F-43B1-99A0}\r\nType   : SATA\r\nStatus : Online\r\nPath   : 0\r\nTarget : 0\r\nLUN ID : 0\r\nLocation Path : PCIROOT(0)#PCI(0D00)#ATA(C00T00L00)\r\nCurrent Read-only State : No\r\nRead-only  : No\r\nBoot Disk  : Yes\r\nPagefile Disk : Yes\r\n`
                };
            }
            if (lower.startsWith('list partition') || lower.startsWith('list part') || lower === 'lis par') {
                return {
                    output: `\r\n  Partition ###  Type              Size     Offset\r\n  -------------  ----------------  -------  -------\r\n  Partition 1    System             100 MB  1024 KB\r\n* Partition 2    Primary            237 GB   101 MB\r\n`
                };
            }
            if (lower.startsWith('select partition') || lower.startsWith('select part') || lower.startsWith('sel par')) {
                this.selectedPart = lower.split(' ').pop() || '1';
                return { output: `\r\nPartition ${this.selectedPart} is now the selected partition.\r\n` };
            }
            if (lower.startsWith('detail partition')) {
                return {
                    output: `\r\nPartition 2\r\nType    : ebd0a0a2-b9e5-4433-87c0-68b6b72699c7\r\nHidden  : No\r\nRequired: No\r\nAttrib  : 0000000000000000\r\nOffset in Bytes: 105906176\r\n\r\n  Volume ###  Ltr  Label        Fs     Type        Size     Status     Info\r\n  ----------  ---  -----------  -----  ----------  -------  ---------  --------\r\n* Volume 0     C   Windows      NTFS   Partition    237 GB  Healthy    Boot\r\n`
                };
            }
            if (lower.startsWith('create partition') || lower.startsWith('create part') || lower.startsWith('cre par')) {
                return { output: `\r\nDiskPart succeeded in creating the specified partition.\r\n` };
            }
            if (lower.startsWith('delete partition') || lower.startsWith('del par')) {
                return { output: `\r\nDiskPart successfully deleted the selected partition.\r\n` };
            }
            if (lower.startsWith('format') || lower.startsWith('for ')) {
                return { output: `\r\n  100 percent completed\r\n\r\nDiskPart successfully formatted the volume.\r\n` };
            }
            if (lower.startsWith('assign') || lower.startsWith('ass ')) {
                const letter = lower.includes('letter=') ? lower.split('letter=')[1].trim().split(' ')[0].toUpperCase() : 'E';
                return { output: `\r\nDiskPart successfully assigned the drive letter or mount point (${letter}:).\r\n` };
            }
            if (lower.startsWith('remove') || lower.startsWith('rem ')) {
                return { output: `\r\nDiskPart successfully removed the drive letter or mount point.\r\n` };
            }
            if (lower.startsWith('list volume') || lower.startsWith('list vol') || lower === 'lis vol') {
                return {
                    output: `\r\n  Volume ###  Ltr  Label        Fs     Type        Size     Status     Info\r\n  ----------  ---  -----------  -----  ----------  -------  ---------  --------\r\n* Volume 0     C   Windows      NTFS   Partition    237 GB  Healthy    System\r\n  Volume 1     E   Dane         NTFS   Partition     64 GB  Healthy\r\n`
                };
            }
            if (lower.startsWith('select volume') || lower.startsWith('select vol') || lower.startsWith('sel vol')) {
                this.selectedVol = lower.split(' ').pop() || '0';
                return { output: `\r\nVolume ${this.selectedVol} is now the selected volume.\r\n` };
            }
            if (lower.startsWith('detail volume')) {
                return {
                    output: `\r\n  Read-only              : No\r\n  Hidden                 : No\r\n  No Default Drive Letter: No\r\n  Shadow Copy            : No\r\n`
                };
            }
            if (lower.startsWith('clean')) {
                return { output: `\r\nDiskPart succeeded in cleaning the disk.\r\n` };
            }
            if (lower.startsWith('convert gpt')) {
                return { output: `\r\nDiskPart successfully converted the selected disk to GPT format.\r\n` };
            }
            if (lower.startsWith('convert mbr')) {
                return { output: `\r\nDiskPart successfully converted the selected disk to MBR format.\r\n` };
            }
            if (lower.startsWith('active')) {
                return { output: `\r\nDiskPart marked the current partition as active.\r\n` };
            }
            if (lower.startsWith('shrink')) {
                return { output: `\r\nDiskPart successfully shrunk the volume by specified amount.\r\n` };
            }
            if (lower.startsWith('extend')) {
                return { output: `\r\nDiskPart successfully extended the volume.\r\n` };
            }
            if (lower.startsWith('rescan')) {
                return { output: `\r\nPlease wait while DiskPart scans your configuration...\r\nDiskPart has finished scanning your configuration.\r\n` };
            }
            if (lower.startsWith('attributes disk clear readonly')) {
                return { output: `\r\nDisk attributes cleared successfully.\r\n` };
            }
            if (lower.startsWith('online disk')) {
                return { output: `\r\nDiskPart successfully onlined the selected disk.\r\n` };
            }
            if (lower.startsWith('offline disk')) {
                return { output: `\r\nDiskPart successfully offlined the selected disk.\r\n` };
            }
            if (lower === 'help' || lower === '?') {
                return {
                    output: `\r\nMicrosoft DiskPart commands:\r\n  ACTIVE      - Mark the selected partition as active.\r\n  ASSIGN      - Assign a drive letter or mount point to the selected volume.\r\n  CLEAN       - Clear the configuration information, or all information, off the disk.\r\n  CONVERT     - Convert between different disk formats (MBR, GPT).\r\n  CREATE      - Create a volume, partition, or virtual disk.\r\n  DELETE      - Delete an object.\r\n  DETAIL      - Provide details about an object.\r\n  EXTEND      - Extend a volume.\r\n  FORMAT      - Format the volume or partition.\r\n  LIST        - Display a list of objects.\r\n  RESCAN      - Rescan the computer looking for disks and volumes.\r\n  SELECT      - Shift the focus to an object.\r\n  SHRINK      - Reduce the size of the selected volume.\r\n  EXIT        - Exit DiskPart.\r\n`
                };
            }
            return { output: `\r\nMicrosoft DiskPart version 10.0.19041.3636\r\nType 'HELP' to see available commands.\r\n` };
        }
    }

    class MysqlShell {
        constructor() {
            this.currentDb = 'zsem_db';
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase().replace(/;$/, '');
            if (lower === 'exit' || lower === 'quit' || lower === '\\q') return { action: 'exit', output: 'Bye\n' };
            if (lower === 'clear' || lower === '\\c') return { action: 'clear', output: '' };

            if (lower === 'show databases' || lower === 'show schemas') {
                return {
                    output: `+--------------------+\n| Database           |\n+--------------------+\n| information_schema |\n| mysql              |\n| performance_schema |\n| sys                |\n| szkola             |\n| zsem_db            |\n+--------------------+\n6 rows in set (0.01 sec)\n`
                };
            }
            if (lower.startsWith('create database') || lower.startsWith('create schema')) {
                const dbName = lower.split(' ')[2] || 'nowa_baza';
                return { output: `Query OK, 1 row affected (0.02 sec)\n` };
            }
            if (lower.startsWith('drop database') || lower.startsWith('drop schema')) {
                return { output: `Query OK, 0 rows affected (0.03 sec)\n` };
            }
            if (lower.startsWith('use ')) {
                this.currentDb = lower.split(' ')[1] || 'zsem_db';
                return { output: `Database changed\n` };
            }
            if (lower === 'show tables') {
                return {
                    output: `+-------------------+\n| Tables_in_${this.currentDb.padEnd(7)} |\n+-------------------+\n| klienci           |\n| oceny             |\n| przedmioty        |\n| uczniowie         |\n+-------------------+\n4 rows in set (0.00 sec)\n`
                };
            }
            if (lower.startsWith('describe ') || lower.startsWith('desc ') || lower.startsWith('show columns from ')) {
                const tbl = lower.split(' ')[1] || 'uczniowie';
                return {
                    output: `+----------+-------------+------+-----+---------+----------------+\n| Field    | Type        | Null | Key | Default | Extra          |\n+----------+-------------+------+-----+---------+----------------+\n| id       | int         | NO   | PRI | NULL    | auto_increment |\n| imie     | varchar(50) | NO   |     | NULL    |                |\n| nazwisko | varchar(50) | NO   |     | NULL    |                |\n| klasa    | varchar(10) | YES  |     | NULL    |                |\n+----------+-------------+------+-----+---------+----------------+\n4 rows in set (0.01 sec)\n`
                };
            }
            if (lower.startsWith('create table')) {
                return { output: `Query OK, 0 rows affected (0.03 sec)\n` };
            }
            if (lower.startsWith('alter table')) {
                return { output: `Query OK, 0 rows affected (0.02 sec)\n` };
            }
            if (lower.startsWith('drop table')) {
                return { output: `Query OK, 0 rows affected (0.02 sec)\n` };
            }
            if (lower.startsWith('create user')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\n` };
            }
            if (lower.startsWith('drop user')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\n` };
            }
            if (lower.startsWith('grant ')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\n` };
            }
            if (lower.startsWith('revoke ')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\n` };
            }
            if (lower.startsWith('show grants')) {
                return {
                    output: `+------------------------------------------------------------------+\n| Grants for user@localhost                                        |\n+------------------------------------------------------------------+\n| GRANT USAGE ON *.* TO \`user\`@\`localhost\`                         |\n| GRANT SELECT, INSERT ON \`szkola\`.* TO \`user\`@\`localhost\`          |\n+------------------------------------------------------------------+\n2 rows in set (0.00 sec)\n`
                };
            }
            if (lower.startsWith('flush privileges')) {
                return { output: `Query OK, 0 rows affected (0.00 sec)\n` };
            }
            if (lower.startsWith('set password')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\n` };
            }
            if (lower.startsWith('insert into')) {
                return { output: `Query OK, 1 row affected (0.02 sec)\n` };
            }
            if (lower.startsWith('update ')) {
                return { output: `Query OK, 1 row affected, 1 row changed (0.02 sec)\nRows matched: 1  Changed: 1  Warnings: 0\n` };
            }
            if (lower.startsWith('delete from')) {
                return { output: `Query OK, 1 row affected (0.02 sec)\n` };
            }
            if (lower.startsWith('select count')) {
                return {
                    output: `+----------+\n| count(*) |\n+----------+\n|        3 |\n+----------+\n1 row in set (0.00 sec)\n`
                };
            }
            if (lower.startsWith('select')) {
                return {
                    output: `+----+-----------+------------+-------+\n| id | imie      | nazwisko   | klasa |\n+----+-----------+------------+-------+\n|  1 | Jan       | Kowalski   | 4P    |\n|  2 | Anna      | Nowak      | 4P    |\n|  3 | Piotr     | Wisniewski | 3I    |\n+----+-----------+------------+-------+\n3 rows in set (0.00 sec)\n`
                };
            }
            if (lower.startsWith('status') || lower === '\\s') {
                return {
                    output: `--------------\nmysql  Ver 8.0.34-0ubuntu0.22.04.1 for Linux on x86_64 ((Ubuntu))\n\nConnection id:          42\nCurrent database:       ${this.currentDb}\nCurrent user:           root@localhost\nSSL:                    Not in use\nCurrent pager:          stdout\nUsing outfile:          ''\nUsing delimiter:        ;\nServer version:         8.0.34-0ubuntu0.22.04.1 (Ubuntu)\nProtocol version:       10\nConnection:             Localhost via UNIX socket\nUNIX socket:            /var/run/mysqld/mysqld.sock\nUptime:                 2 hours 15 min 22 sec\n--------------\n`
                };
            }
            if (lower.startsWith('source ') || lower.startsWith('\\.')) {
                return { output: `Query OK, 0 rows affected (0.01 sec)\nQuery OK, 1 row affected (0.02 sec)\n` };
            }
            if (lower === 'help' || lower === '\\h' || lower === '?') {
                return {
                    output: `List of all MySQL commands:\n?         (\\?) Synonym for 'help'.\nclear     (\\c) Clear the current input statement.\nexit      (\\q) Exit mysql. Same as quit.\nhelp      (\\h) Display this help.\nquit      (\\q) Quit mysql.\nstatus    (\\s) Get status information from the server.\n`
                };
            }
            return { output: `Query OK, 1 row affected (0.01 sec)\n` };
        }
    }

    class SshShell {
        constructor(term, user, host) {
            this.term = term;
            this.user = user || 'student';
            this.host = host || '192.168.1.100';
            this.prevUser = term.net.state.currentUserLinux;
            term.net.state.currentUserLinux = this.user;
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();
            if (lower === 'exit' || lower === 'logout' || lower === 'quit') {
                this.term.net.state.currentUserLinux = this.prevUser;
                return { action: 'exit', output: `Connection to ${this.host} closed.\n` };
            }
            if (lower === 'clear') return { action: 'clear', output: '' };

            const parsed = this.term.parseArgs(raw);
            const cmdName = parsed[0]?.toLowerCase();
            const cmdArgs = parsed.slice(1);
            const handler = LINUX_COMMANDS[cmdName];
            if (handler) {
                const res = handler(cmdArgs, this.term);
                return { output: res === '__CLEAR__' ? '' : (res || '') + '\n' };
            }
            return { output: `bash: ${cmdName}: command not found\n` };
        }
    }

    class NslookupShell {
        constructor(term) {
            this.term = term;
            this.queryType = 'A';
            this.server = term.net.state.dns[0] || '8.8.8.8';
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();
            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: '' };
            if (lower.startsWith('server ')) {
                this.server = lower.split(' ')[1] || '8.8.8.8';
                return { output: `Default Server:  [${this.server}]\r\nAddress:  ${this.server}\r\n\r\n` };
            }
            if (lower.startsWith('set type=') || lower.startsWith('set q=')) {
                this.queryType = lower.split('=')[1].toUpperCase();
                return { output: `Query type set to: ${this.queryType}\r\n` };
            }

            let ip = '142.250.187.195';
            if (lower.includes('zsem.local')) ip = '192.168.1.100';
            else if (lower.includes('localhost')) ip = '127.0.0.1';

            if (this.queryType === 'MX') {
                return {
                    output: `Server:  [${this.server}]\r\nAddress:  ${this.server}\r\n\r\n${raw}   MX preference = 10, mail exchanger = mail.${raw}\r\n`
                };
            }
            if (this.queryType === 'NS') {
                return {
                    output: `Server:  [${this.server}]\r\nAddress:  ${this.server}\r\n\r\n${raw}   nameserver = ns1.${raw}\r\n`
                };
            }
            if (this.queryType === 'SOA') {
                return {
                    output: `Server:  [${this.server}]\r\nAddress:  ${this.server}\r\n\r\n${raw}\r\n        primary name server = ns1.${raw}\r\n        responsible mail addr = hostmaster.${raw}\r\n        serial  = 2026081701\r\n        refresh = 3600 (1 hour)\r\n        retry   = 600 (10 mins)\r\n        expire  = 1209600 (14 days)\r\n        default TTL = 3600 (1 hour)\r\n`
                };
            }

            return {
                output: `Server:  [${this.server}]\r\nAddress:  ${this.server}\r\n\r\nNon-authoritative answer:\r\nName:    ${raw}\r\nAddress:  ${ip}\r\n`
            };
        }
    }

    class PythonShell {
        execute(cmd) {
            const raw = cmd.trim();
            if (raw === 'exit()' || raw === 'quit()' || raw === 'exit' || raw === 'quit') return { action: 'exit', output: '' };
            if (raw.startsWith('print(')) {
                const match = raw.match(/print\((.*)\)/);
                if (match) {
                    try {
                        /* eslint-disable no-eval */
                        const evaluated = eval(match[1]);
                        return { output: String(evaluated) + '\n' };
                    } catch (e) {
                        return { output: match[1].replace(/['"]/g, '') + '\n' };
                    }
                }
            }
            if (raw.startsWith('import ')) {
                return { output: '' };
            }
            if (raw === 'help()' || raw === 'help') {
                return { output: 'Welcome to Python 3.10 interactive help utility!\nType any expression (e.g. 2 + 2, len([1,2,3])) to evaluate.\n' };
            }
            try {
                /* eslint-disable no-eval */
                const res = eval(raw);
                return { output: (res !== undefined ? String(res) : '') + '\n' };
            } catch (e) {
                return { output: `SyntaxError: ${e.message}\n` };
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 4. LINUX COMMANDS ENGINE (GNU/BASH)
    // ════════════════════════════════════════════════════════════════════════════

    const LINUX_COMMANDS = {
        'pwd': (a, term) => term.vfs.currentDirLinux,

        'cd': (a, term) => {
            const target = a[0] || '~';
            const norm = term.vfs.normalizePath(target, false);
            const node = term.vfs.getNode(norm, false);
            if (!node) return `bash: cd: ${target}: No such file or directory`;
            if (node.type !== 'dir') return `bash: cd: ${target}: Not a directory`;
            term.vfs.currentDirLinux = norm;
            return '';
        },

        'ls': (a, term) => {
            const showAll = a.some(arg => arg.startsWith('-') && (arg.includes('a') || arg.includes('A')));
            const showLong = a.some(arg => arg.startsWith('-') && arg.includes('l'));
            const targetPath = a.find(arg => !arg.startsWith('-')) || '.';
            const node = term.vfs.getNode(targetPath, false);

            if (!node) return `ls: cannot access '${targetPath}': No such file or directory`;
            if (node.type === 'file') {
                return showLong ? `-rw-r--r-- 1 ${node.owner || 'student'} ${node.group || 'student'} ${node.size || 0} ${node.name}` : node.name;
            }

            const items = Object.values(node.children || {});
            const filtered = showAll ? [{ name: '.', type: 'dir', permissions: '0755', owner: 'root', group: 'root', size: 4096 }, { name: '..', type: 'dir', permissions: '0755', owner: 'root', group: 'root', size: 4096 }, ...items] : items.filter(i => !i.name.startsWith('.'));

            if (showLong) {
                let out = `total ${filtered.length * 4}\n`;
                out += filtered.map(i => {
                    const isDir = i.type === 'dir' ? 'd' : (i.type === 'symlink' ? 'l' : '-');
                    const perm = i.permissions ? (i.permissions === '0777' ? 'rwxrwxrwx' : (i.permissions.includes('750') ? 'rwxr-x---' : (i.permissions.includes('700') ? 'rwx------' : 'rwxr-xr-x'))) : 'rw-r--r--';
                    const linkTarget = i.type === 'symlink' ? ` -> ${i.target}` : '';
                    return `${isDir}${perm} 1 ${i.owner || 'student'} ${i.group || 'student'} ${String(i.size || 4096).padStart(6)} ${i.name}${linkTarget}`;
                }).join('\n');
                return out;
            }
            return filtered.map(i => i.name + (i.type === 'dir' ? '/' : '')).join('  ');
        },

        'mkdir': (a, term) => {
            if (!a.length) return 'mkdir: missing operand';
            const recursive = a.includes('-p');
            const paths = a.filter(arg => !arg.startsWith('-'));
            if (!paths.length) return 'mkdir: missing operand';
            paths.forEach(p => term.vfs.createDirectory(p, recursive, false));
            return '';
        },

        'rmdir': (a, term) => {
            if (!a.length) return 'rmdir: missing operand';
            const paths = a.filter(arg => !arg.startsWith('-'));
            for (const p of paths) {
                const ok = term.vfs.removeNode(p, false, false);
                if (!ok) return `rmdir: failed to remove '${p}': Directory not empty or not found`;
            }
            return '';
        },

        'touch': (a, term) => {
            if (!a.length) return 'touch: missing file operand';
            const files = a.filter(arg => !arg.startsWith('-'));
            files.forEach(f => term.vfs.createFile(f, '', false));
            return '';
        },

        'rm': (a, term) => {
            if (!a.length) return 'rm: missing operand';
            const recursive = a.some(arg => arg.startsWith('-') && (arg.includes('r') || arg.includes('R')));
            const paths = a.filter(arg => !arg.startsWith('-'));
            if (!paths.length) return 'rm: missing operand';
            for (const p of paths) {
                const ok = term.vfs.removeNode(p, recursive, false);
                if (!ok && !a.includes('-f') && !a.includes('-rf')) return `rm: cannot remove '${p}': No such file or directory`;
            }
            return '';
        },

        'cp': (a, term) => {
            const clean = a.filter(arg => !arg.startsWith('-'));
            if (clean.length < 2) return 'cp: missing destination file operand';
            const recursive = a.some(arg => arg.startsWith('-') && (arg.includes('r') || arg.includes('R')));
            const ok = term.vfs.copyNode(clean[0], clean[1], recursive, false);
            return ok ? '' : `cp: cannot copy '${clean[0]}' to '${clean[1]}'`;
        },

        'mv': (a, term) => {
            const clean = a.filter(arg => !arg.startsWith('-'));
            if (clean.length < 2) return 'mv: missing destination file operand';
            const ok = term.vfs.moveNode(clean[0], clean[1], false);
            return ok ? '' : `mv: cannot move '${clean[0]}' to '${clean[1]}'`;
        },

        'cat': (a, term, pipeInput = '') => {
            const showLineNums = a.includes('-n');
            const files = a.filter(arg => !arg.startsWith('-') && arg !== '-');
            let content = '';

            if (!files.length && pipeInput) {
                content = pipeInput;
            } else if (files.length) {
                for (const file of files) {
                    const node = term.vfs.getNode(file, false);
                    if (!node) return `cat: ${file}: No such file or directory`;
                    if (node.type === 'dir') return `cat: ${file}: Is a directory`;
                    content += (content ? '\n' : '') + (node.content || '');
                }
            }
            if (showLineNums) {
                return content.split('\n').map((l, i) => `     ${i + 1}  ${l}`).join('\n');
            }
            return content;
        },

        'head': (a, term, pipeInput = '') => {
            const numIdx = a.indexOf('-n');
            const count = numIdx !== -1 ? parseInt(a[numIdx + 1], 10) || 10 : 10;
            const file = a.find(arg => !arg.startsWith('-') && arg !== String(count));
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (!node) return `head: cannot open '${file}' for reading: No such file or directory`;
                text = node.content || '';
            }
            return (text || '').split('\n').slice(0, count).join('\n');
        },

        'tail': (a, term, pipeInput = '') => {
            const numIdx = a.indexOf('-n');
            const count = numIdx !== -1 ? parseInt(a[numIdx + 1], 10) || 10 : 10;
            const file = a.find(arg => !arg.startsWith('-') && arg !== String(count));
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (!node) return `tail: cannot open '${file}' for reading: No such file or directory`;
                text = node.content || '';
            }
            const lines = (text || '').split('\n');
            return lines.slice(-count).join('\n');
        },

        'grep': (a, term, pipeInput = '') => {
            const ignoreCase = a.includes('-i');
            const invert = a.includes('-v');
            const showLine = a.includes('-n');
            const countOnly = a.includes('-c');
            const cleanArgs = a.filter(arg => !arg.startsWith('-'));
            const pattern = cleanArgs[0];
            const file = cleanArgs[1];

            if (!pattern) return 'Usage: grep [OPTION]... PATTERNS [FILE]...';
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (!node) return `grep: ${file}: No such file or directory`;
                text = node.content || '';
            }

            const lines = (text || '').split('\n');
            let regex;
            try {
                regex = new RegExp(pattern, ignoreCase ? 'i' : '');
            } catch (e) {
                regex = new RegExp(pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), ignoreCase ? 'i' : '');
            }

            const matched = lines
                .map((l, i) => ({ l, num: i + 1, match: regex.test(l) }))
                .filter(item => invert ? !item.match : item.match);

            if (countOnly) return String(matched.length);
            return matched.map(item => showLine ? `${item.num}:${item.l}` : item.l).join('\n');
        },

        'wc': (a, term, pipeInput = '') => {
            const file = a.find(arg => !arg.startsWith('-'));
            let content = pipeInput;
            let label = '';
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (!node) return `wc: ${file}: No such file or directory`;
                content = node.content || '';
                label = ' ' + file;
            }
            const lines = content ? content.split('\n').length : 0;
            const words = content ? content.split(/\s+/).filter(Boolean).length : 0;
            const bytes = content ? content.length : 0;
            if (a.includes('-l')) return `${lines}${label}`;
            if (a.includes('-w')) return `${words}${label}`;
            if (a.includes('-c')) return `${bytes}${label}`;
            return `${lines} ${words} ${bytes}${label}`;
        },

        'sort': (a, term, pipeInput = '') => {
            const reverse = a.includes('-r');
            const numeric = a.includes('-n');
            const unique = a.includes('-u');
            const file = a.find(arg => !arg.startsWith('-'));
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (node) text = node.content || '';
            }
            let lines = (text || '').split('\n');
            if (unique) lines = Array.from(new Set(lines));
            lines.sort((x, y) => {
                if (numeric) {
                    const nx = parseFloat(x) || 0;
                    const ny = parseFloat(y) || 0;
                    return nx - ny;
                }
                return x.localeCompare(y);
            });
            if (reverse) lines.reverse();
            return lines.join('\n');
        },

        'uniq': (a, term, pipeInput = '') => {
            const count = a.includes('-c');
            const file = a.find(arg => !arg.startsWith('-'));
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (node) text = node.content || '';
            }
            const lines = (text || '').split('\n');
            const out = [];
            let last = null;
            let lastCount = 0;
            for (const l of lines) {
                if (l === last) {
                    lastCount++;
                } else {
                    if (last !== null) {
                        out.push(count ? `   ${lastCount} ${last}` : last);
                    }
                    last = l;
                    lastCount = 1;
                }
            }
            if (last !== null) out.push(count ? `   ${lastCount} ${last}` : last);
            return out.join('\n');
        },

        'cut': (a, term, pipeInput = '') => {
            let delim = '\t';
            const dIdx = a.findIndex(arg => arg.startsWith('-d'));
            if (dIdx !== -1) {
                delim = a[dIdx].length > 2 ? a[dIdx].slice(2).replace(/['"]/g, '') : (a[dIdx + 1]?.replace(/['"]/g, '') || '\t');
            }
            let field = 1;
            const fIdx = a.findIndex(arg => arg.startsWith('-f'));
            if (fIdx !== -1) {
                field = parseInt(a[fIdx].length > 2 ? a[fIdx].slice(2) : a[fIdx + 1], 10) || 1;
            }
            const file = a.find(arg => !arg.startsWith('-') && arg !== a[dIdx + 1] && arg !== a[fIdx + 1]);
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (node) text = node.content || '';
            }
            return (text || '').split('\n').map(line => {
                const parts = line.split(delim);
                return parts[field - 1] || '';
            }).join('\n');
        },

        'tr': (a, term, pipeInput = '') => {
            if (a.includes('-d')) {
                const target = a[a.indexOf('-d') + 1]?.replace(/['"]/g, '') || '';
                return (pipeInput || '').split(target).join('');
            }
            if (a.length >= 2) {
                const src = a[0].replace(/['"]/g, '');
                const dst = a[1].replace(/['"]/g, '');
                if (src === 'a-z' && dst === 'A-Z') return (pipeInput || '').toUpperCase();
                if (src === 'A-Z' && dst === 'a-z') return (pipeInput || '').toLowerCase();
                return (pipeInput || '').split(src).join(dst);
            }
            return pipeInput || '';
        },

        'sed': (a, term, pipeInput = '') => {
            const expr = a.find(arg => arg.startsWith('s/')) || a.find(arg => arg.startsWith('s:')) || a[0];
            const file = a.find(arg => !arg.startsWith('-') && arg !== expr);
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (node) text = node.content || '';
            }
            if (expr && expr.startsWith('s/')) {
                const parts = expr.split('/');
                const from = parts[1];
                const to = parts[2];
                const flags = parts[3] || 'g';
                const regex = new RegExp(from, flags);
                return (text || '').replace(regex, to);
            }
            return text || '';
        },

        'awk': (a, term, pipeInput = '') => {
            let delim = /\s+/;
            const fIdx = a.indexOf('-F');
            if (fIdx !== -1 && a[fIdx + 1]) delim = a[fIdx + 1].replace(/['"]/g, '');
            const script = a.find(arg => arg.includes('{') && arg.includes('}')) || '{print $0}';
            const file = a.find(arg => !arg.startsWith('-') && arg !== a[fIdx + 1] && !arg.includes('{'));
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, false);
                if (node) text = node.content || '';
            }
            return (text || '').split('\n').map(line => {
                const cols = line.split(delim);
                if (script.includes('$1') && !script.includes('$2')) return cols[0] || '';
                if (script.includes('$2')) return cols[1] || '';
                if (script.includes('$NF')) return cols[cols.length - 1] || '';
                return line;
            }).join('\n');
        },

        'tee': (a, term, pipeInput = '') => {
            const isAppend = a.includes('-a');
            const file = a.find(arg => !arg.startsWith('-'));
            if (file && pipeInput) {
                if (isAppend) {
                    const existing = term.vfs.getNode(file, false)?.content || '';
                    term.vfs.createFile(file, existing + '\n' + pipeInput, false);
                } else {
                    term.vfs.createFile(file, pipeInput, false);
                }
            }
            return pipeInput;
        },

        'more': (a, term, pipeInput = '') => LINUX_COMMANDS.cat(a, term, pipeInput),
        'less': (a, term, pipeInput = '') => LINUX_COMMANDS.cat(a, term, pipeInput),

        'find': (a, term) => {
            const path = a.find(arg => !arg.startsWith('-')) || '.';
            const nameIdx = a.indexOf('-name');
            const targetName = nameIdx !== -1 ? a[nameIdx + 1]?.replace(/['"]/g, '') : null;
            const permIdx = a.indexOf('-perm');
            const targetPerm = permIdx !== -1 ? a[permIdx + 1] : null;
            const node = term.vfs.getNode(path, false);
            if (!node) return `find: '${path}': No such file or directory`;

            const results = [];
            function traverse(curr, currPath) {
                let match = true;
                if (targetName && !curr.name.includes(targetName.replace(/\*/g, ''))) match = false;
                if (targetPerm && curr.permissions && !curr.permissions.includes(targetPerm.replace(/^-/, ''))) match = false;
                if (match) results.push(currPath);
                if (curr.type === 'dir' && curr.children) {
                    Object.values(curr.children).forEach(child => {
                        traverse(child, (currPath === '/' ? '' : currPath) + '/' + child.name);
                    });
                }
            }
            traverse(node, path);
            return results.join('\n');
        },

        'tree': (a, term) => {
            const path = a.find(arg => !arg.startsWith('-')) || '.';
            const node = term.vfs.getNode(path, false);
            if (!node || node.type !== 'dir') return `${path} [error opening dir]`;
            return `.\n` + term.vfs.generateTreeAscii(node);
        },

        'echo': (a, term, pipeInput = '') => {
            if (!a.length && pipeInput) return pipeInput;
            return a.join(' ');
        },

        'diff': (a, term) => {
            if (a.length < 2) return 'diff: missing operand';
            const n1 = term.vfs.getNode(a[0], false);
            const n2 = term.vfs.getNode(a[1], false);
            if (!n1) return `diff: ${a[0]}: No such file or directory`;
            if (!n2) return `diff: ${a[1]}: No such file or directory`;
            if ((n1.content || '') === (n2.content || '')) return '';
            return `1c1\n< ${n1.content || ''}\n---\n> ${n2.content || ''}`;
        },

        'file': (a, term) => {
            const target = a[0] || '.';
            const node = term.vfs.getNode(target, false);
            if (!node) return `${target}: cannot open (No such file or directory)`;
            if (node.type === 'dir') return `${target}: directory`;
            if (node.type === 'symlink') return `${target}: symbolic link to ${node.target}`;
            if (target.endsWith('.sh') || (node.content && node.content.startsWith('#!/bin/bash'))) return `${target}: Bourne-Again shell script, ASCII text executable`;
            if (target.endsWith('.py')) return `${target}: Python script, ASCII text executable`;
            return `${target}: ASCII text`;
        },

        'which': (a) => {
            const cmd = a[0] || 'bash';
            if (['bash', 'ls', 'cat', 'chmod', 'chown', 'grep', 'touch', 'rm', 'mkdir', 'ps', 'kill', 'systemctl', 'ip', 'ifconfig', 'nano', 'python', 'mysql'].includes(cmd)) {
                return `/usr/bin/${cmd}`;
            }
            return '';
        },
        'whereis': (a) => {
            const cmd = a[0] || 'bash';
            return `${cmd}: /usr/bin/${cmd} /usr/share/man/man1/${cmd}.1.gz`;
        },
        'type': (a) => {
            const cmd = a[0] || 'ls';
            return `${cmd} is /usr/bin/${cmd}`;
        },

        // ── Process Management ──
        'ps': (a) => {
            return `USER         PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND\nroot           1  0.0  0.1 168432 11840 ?        Ss   08:00   0:02 /sbin/init\nroot         650  0.0  0.1  28412  8920 ?        Ss   08:00   0:00 /lib/systemd/systemd-logind\nroot        1180  0.0  0.3 148920 24100 ?        Ssl  08:00   0:01 /usr/sbin/named -u bind\nroot        1240  0.0  0.1  72100  9200 ?        Ss   08:00   0:00 /usr/sbin/sshd -D\nroot        1350  0.0  0.2  98400 18200 ?        Ss   08:00   0:00 /usr/sbin/smbd --foreground --no-process-group\nroot        1420  0.0  0.3 198420 28400 ?        Ss   08:00   0:01 /usr/sbin/apache2 -k start\nwww-data    1421  0.0  0.2 198450 16200 ?        S    08:00   0:00 /usr/sbin/apache2 -k start\nmysql       1510  0.1  1.8 1420900 148200 ?      Ssl  08:00   0:08 /usr/sbin/mysqld\nstudent     2100  0.0  0.1  22450  6800 pts/0    Ss   08:10   0:00 -bash\nstudent     2450  0.0  0.0  18900  3200 pts/0    R+   08:35   0:00 ps ${a.join(' ')}`;
        },

        'top': () => `top - 08:35:10 up 2 days,  4:12,  1 user,  load average: 0.14, 0.08, 0.05\nTasks: 112 total,   1 running, 111 sleeping,   0 stopped,   0 zombie\n%Cpu(s):  1.2 us,  0.8 sy,  0.0 ni, 97.8 id,  0.2 wa,  0.0 hi,  0.0 si,  0.0 st\nMiB Mem :   7975.9 total,   4111.8 free,   1879.4 used,   1984.7 buff/cache\nMiB Swap:   2048.0 total,   2048.0 free,      0.0 used.   5782.6 avail Mem \n\n    PID USER      PR  NI    VIRT    RES    SHR S  %CPU  %MEM     TIME+ COMMAND\n   1510 mysql     20   0 1420900 148200  34120 S   1.3   1.8   0:08.42 mysqld\n   1420 root      20   0  198420  28400  14200 S   0.7   0.3   0:01.15 apache2\n   1180 bind      20   0  148920  24100  12400 S   0.3   0.3   0:01.02 named\n   2100 student   20   0   22450   6800   4100 S   0.0   0.1   0:00.32 bash`,
        'htop': () => LINUX_COMMANDS.top(),

        'kill': (a, term) => {
            const pid = a.find(arg => /^\d+$/.test(arg)) || '1420';
            if (pid === '1420' || pid === '1421') {
                if (term.net.state.services.apache2) term.net.state.services.apache2.status = 'STOPPED';
                term.net.save();
            }
            return '';
        },
        'killall': (a, term) => {
            const name = a.find(arg => !arg.startsWith('-'));
            if (name && term.net.state.services[name]) {
                term.net.state.services[name].status = 'STOPPED';
                term.net.save();
            }
            return '';
        },
        'pkill': (a, term) => LINUX_COMMANDS.killall(a, term),

        // ── Permissions & Ownership ──
        'chmod': (a, term) => {
            const clean = a.filter(arg => !arg.startsWith('-'));
            if (clean.length < 2) return 'Usage: chmod [OPTION]... MODE[,MODE]... FILE...';
            const mode = clean[0];
            const targetPath = clean[1];
            const recursive = a.some(arg => arg.startsWith('-') && (arg.includes('R') || arg.includes('r')));

            function applyMode(node) {
                if (!node) return;
                if (/^\d{3,4}$/.test(mode)) {
                    node.permissions = mode.length === 3 ? '0' + mode : mode;
                } else if (mode.includes('+x')) {
                    node.permissions = '0755';
                } else if (mode.includes('-x')) {
                    node.permissions = '0644';
                } else {
                    node.permissions = '0755';
                }
                if (recursive && node.children) {
                    Object.values(node.children).forEach(applyMode);
                }
            }

            const target = term.vfs.getNode(targetPath, false);
            if (!target) return `chmod: cannot access '${targetPath}': No such file or directory`;
            applyMode(target);
            term.vfs.save();
            return '';
        },

        'chown': (a, term) => {
            const clean = a.filter(arg => !arg.startsWith('-'));
            if (clean.length < 2) return 'Usage: chown [OPTION]... [OWNER][:[GROUP]] FILE...';
            const spec = clean[0];
            const targetPath = clean[1];
            const recursive = a.some(arg => arg.startsWith('-') && (arg.includes('R') || arg.includes('r')));

            const parts = spec.split(/[:.]/);
            const user = parts[0] || null;
            const group = parts[1] || null;

            function applyOwner(node) {
                if (!node) return;
                if (user) node.owner = user;
                if (group) node.group = group;
                if (recursive && node.children) {
                    Object.values(node.children).forEach(applyOwner);
                }
            }

            const target = term.vfs.getNode(targetPath, false);
            if (!target) return `chown: cannot access '${targetPath}': No such file or directory`;
            applyOwner(target);
            term.vfs.save();
            return '';
        },

        'chgrp': (a, term) => {
            const clean = a.filter(arg => !arg.startsWith('-'));
            if (clean.length < 2) return 'Usage: chgrp [OPTION]... GROUP FILE...';
            return LINUX_COMMANDS.chown([`:${clean[0]}`, clean[1], ...a.filter(arg => arg.startsWith('-'))], term);
        },

        'umask': (a) => a[0] ? '' : '0022',
        'ln': (a, term) => {
            if (a.includes('-s')) {
                const clean = a.filter(arg => !arg.startsWith('-'));
                if (clean.length >= 2) {
                    term.vfs.createFile(clean[1], '', false);
                    const n = term.vfs.getNode(clean[1], false);
                    if (n) { n.type = 'symlink'; n.target = clean[0]; n.permissions = '0777'; }
                    term.vfs.save();
                }
            }
            return '';
        },
        'stat': (a, term) => {
            const file = a[0] || '.';
            const node = term.vfs.getNode(file, false);
            if (!node) return `stat: cannot stat '${file}': No such file or directory`;
            return `  File: ${node.name}\n  Size: ${node.size || 4096}        Blocks: 8          IO Block: 4096   ${node.type === 'dir' ? 'directory' : 'regular file'}\nDevice: 801h/2049d      Inode: 198421      Links: 1\nAccess: (${node.permissions || '0755'}/${node.type === 'dir' ? 'drwxr-xr-x' : '-rw-r--r--'})  Uid: ( 1000/ ${node.owner || 'student'})   Gid: ( 1000/ ${node.group || 'student'})\nAccess: 2026-08-17 08:30:00.000000000 +0000\nModify: 2026-08-17 08:30:00.000000000 +0000\nChange: 2026-08-17 08:30:00.000000000 +0000`;
        },

        // ── User Management ──
        'useradd': (a, term) => {
            const name = a.find(arg => !arg.startsWith('-')) || 'nowy_uzytkownik';
            const passwdNode = term.vfs.getNode('/etc/passwd', false);
            if (passwdNode) {
                passwdNode.content += `${name}:x:1002:1002::/home/${name}:/bin/bash\n`;
            }
            term.vfs.createDirectory(`/home/${name}`, true, false);
            term.vfs.save();
            return '';
        },
        'adduser': (a, term) => LINUX_COMMANDS.useradd(a, term),

        'usermod': (a, term) => {
            const grpIdx = a.indexOf('-G') !== -1 ? a.indexOf('-G') : a.indexOf('-aG');
            const group = grpIdx !== -1 ? a[grpIdx + 1] : null;
            const user = a[a.length - 1];
            if (group && user) {
                const groupNode = term.vfs.getNode('/etc/group', false);
                if (groupNode && groupNode.content.includes(`${group}:`)) {
                    groupNode.content = groupNode.content.replace(new RegExp(`(${group}:.*)`), `$1,${user}`);
                    term.vfs.save();
                }
            }
            return '';
        },

        'userdel': (a, term) => {
            const name = a.find(arg => !arg.startsWith('-'));
            if (name) {
                const passwdNode = term.vfs.getNode('/etc/passwd', false);
                if (passwdNode) {
                    passwdNode.content = passwdNode.content.split('\n').filter(l => !l.startsWith(`${name}:`)).join('\n');
                }
                if (a.includes('-r')) term.vfs.removeNode(`/home/${name}`, true, false);
                term.vfs.save();
            }
            return '';
        },

        'groupadd': (a, term) => {
            const name = a.find(arg => !arg.startsWith('-')) || 'nowa_grupa';
            const grpNode = term.vfs.getNode('/etc/group', false);
            if (grpNode) {
                grpNode.content += `${name}:x:1005:\n`;
                term.vfs.save();
            }
            return '';
        },
        'groupdel': (a, term) => {
            const name = a.find(arg => !arg.startsWith('-'));
            if (name) {
                const grpNode = term.vfs.getNode('/etc/group', false);
                if (grpNode) {
                    grpNode.content = grpNode.content.split('\n').filter(l => !l.startsWith(`${name}:`)).join('\n');
                    term.vfs.save();
                }
            }
            return '';
        },
        'gpasswd': (a, term) => {
            if (a.includes('-a')) {
                const u = a[a.indexOf('-a') + 1];
                const g = a[a.indexOf('-a') + 2];
                return LINUX_COMMANDS.usermod(['-aG', g, u], term);
            }
            return '';
        },

        'passwd': () => `passwd: password updated successfully\n`,
        'whoami': (a, term) => term.net.state.currentUserLinux,

        'id': (a, term) => {
            const u = a.find(arg => !arg.startsWith('-')) || term.net.state.currentUserLinux;
            if (u === 'root') return 'uid=0(root) gid=0(root) groups=0(root)';
            if (u === 'marek') return 'uid=1001(marek) gid=1001(marek) groups=1001(marek),27(sudo)';
            return 'uid=1000(student) gid=1000(student) groups=1000(student),27(sudo),100(users),110(admin)';
        },
        'groups': (a, term) => {
            const u = a[0] || term.net.state.currentUserLinux;
            if (u === 'root') return 'root';
            return `${u} : ${u} adm sudo users admin`;
        },

        'su': (a, term) => {
            const target = a.find(arg => !arg.startsWith('-')) || 'root';
            term.net.state.currentUserLinux = target;
            term.vfs.currentDirLinux = target === 'root' ? '/root' : `/home/${target}`;
            term.net.save();
            return '';
        },

        'sudo': (a, term, pipeInput = '') => {
            if (!a.length) return 'usage: sudo command';
            if (a[0] === 'su' || a[0] === '-i' || a[0] === '-s') {
                return LINUX_COMMANDS.su(['root'], term);
            }
            const cmd = a[0];
            const cmdArgs = a.slice(1);
            if (LINUX_COMMANDS[cmd]) {
                const prev = term.net.state.currentUserLinux;
                term.net.state.currentUserLinux = 'root';
                const res = LINUX_COMMANDS[cmd](cmdArgs, term, pipeInput);
                term.net.state.currentUserLinux = prev;
                return res;
            }
            return `sudo: ${cmd}: command not found`;
        },

        // ── Storage, Disks & Filesystems ──
        'lsblk': () => `NAME                    MAJ:MIN RM  SIZE RO TYPE MOUNTPOINTS\nsda                       8:0    0   30G  0 disk \n├─sda1                    8:1    0  512M  0 part /boot\n└─sda2                    8:2    0 29.5G  0 part /\nsdb                       8:16   0   20G  0 disk \n└─sdb1                    8:17   0   20G  0 part \n  └─vg_dane-lv_dane     253:0    0   10G  0 lvm  /mnt/dane\nsdc                       8:32   0   20G  0 disk \nsr0                      11:0    1 1024M  0 rom  `,

        'fdisk': (a) => {
            if (a.includes('-l')) {
                return `Disk /dev/sda: 30 GiB, 32212254720 bytes, 62914560 sectors\nUnits: sectors of 1 * 512 = 512 bytes\nDevice     Boot   Start      End  Sectors  Size Id Type\n/dev/sda1  *       2048  1050623  1048576  512M 83 Linux\n/dev/sda2       1050624 62914559 61863936 29.5G 83 Linux\n\nDisk /dev/sdb: 20 GiB, 21474836480 bytes, 41943040 sectors\nDevice     Boot Start      End  Sectors Size Id Type\n/dev/sdb1        2048 41943039 41940992  20G 8e Linux LVM`;
            }
            return `Welcome to fdisk (util-linux 2.37.2).\nChanges will remain in memory only, until you decide to write them.\nCommand (m for help): Partition 1 of type Linux and size 20 GiB created.\nSyncing disks.`;
        },

        'blkid': () => `/dev/sda1: UUID="7a21b8c0-1284-4e92-91af-31a89c201201" BLOCK_SIZE="4096" TYPE="ext4" PARTUUID="0001a2f4-01"\n/dev/sda2: UUID="c94b2810-7219-482a-bc12-984210a4e812" BLOCK_SIZE="4096" TYPE="ext4" PARTUUID="0001a2f4-02"\n/dev/sdb1: UUID="uYh9-3kLk-9N2a-Pl71-98a2" TYPE="LVM2_member" PARTUUID="0002b3c1-01"\n/dev/mapper/vg_dane-lv_dane: UUID="98dfa283-4a11-47c1-a209-1fa329b8c012" BLOCK_SIZE="4096" TYPE="ext4"`,

        'parted': (a) => {
            return `Model: ATA VBOX HARDDISK (scsi)\nDisk /dev/sda: 32.2GB\nSector size (logical/physical): 512B/512B\nPartition Table: msdos\nDisk Flags: \n\nNumber  Start   End     Size    Type     File system  Flags\n 1      1048kB  538MB   537MB   primary  ext4         boot\n 2      538MB   32.2GB  31.7GB  primary  ext4`;
        },

        'mkfs': (a) => LINUX_COMMANDS['mkfs.ext4'](a),
        'mkfs.ext4': (a) => `mke2fs 1.46.5 (30-Dec-2021)\nCreating filesystem with 5242880 4k blocks and 1310720 inodes\nFilesystem UUID: 98dfa283-4a11-47c1-a209-1fa329b8c012\nWriting inode tables: done\nCreating journal (32768 blocks): done\nWriting superblocks and filesystem accounting information: done\n`,
        'mkfs.vfat': () => `mkfs.fat 4.2 (2021-01-31)\n`,
        'mkfs.ntfs': () => `The NTFS output volume was created successfully.\n`,
        'mount': (a) => {
            if (!a.length) return `/dev/sda2 on / type ext4 (rw,relatime,errors=remount-ro)\n/dev/sda1 on /boot type ext4 (rw,relatime)\n/dev/mapper/vg_dane-lv_dane on /mnt/dane type ext4 (rw,relatime)\ntmpfs on /run type tmpfs (rw,nosuid,noexec,relatime,size=805060k,mode=755)`;
            return '';
        },
        'umount': () => '',
        'tune2fs': (a) => `tune2fs 1.46.5 (30-Dec-2021)\nFilesystem volume name:   <none>\nLast mounted on:          /\nFilesystem UUID:          c94b2810-7219-482a-bc12-984210a4e812\nFilesystem magic number:  0xEF53\nFilesystem state:         clean\n`,

        // ── LVM Management ──
        'pvcreate': (a) => `  Physical volume "${a[0] || '/dev/sdb1'}" successfully created.`,
        'pvs': () => `  PV         VG        Fmt  Attr PSize   PFree \n  /dev/sdb1  vg_dane   lvm2 a--  20.00g  10.00g\n  /dev/sdc1  vg_dane   lvm2 a--  20.00g  20.00g`,
        'pvdisplay': () => `  --- Physical volume ---\n  PV Name               /dev/sdb1\n  VG Name               vg_dane\n  PV Size               20.00 GiB\n  Allocatable           yes\n  PE Size               4.00 MiB\n  Total PE              5119\n  Allocated PE          2560\n  PV UUID               uYh9-3kLk-9N2a-Pl71`,
        'vgcreate': (a) => `  Volume group "${a[0] || 'vg_dane'}" successfully created`,
        'vgs': () => `  VG        #PV #LV #SN Attr   VSize  VFree \n  vg_dane     2   1   0 wz--n- 39.99g 29.99g`,
        'vgdisplay': () => `  --- Volume group ---\n  VG Name               vg_dane\n  Format                lvm2\n  VG Access             read/write\n  VG Status             resizable\n  Cur LV                1\n  Cur PV                2\n  VG Size               39.99 GiB\n  Alloc PE / Size       2560 / 10.00 GiB\n  Free  PE / Size       7678 / 29.99 GiB`,
        'lvcreate': (a) => {
            const nameIdx = a.indexOf('-n');
            const name = nameIdx !== -1 ? a[nameIdx + 1] : 'lv_dane';
            return `  Logical volume "${name}" created.`;
        },
        'lvs': () => `  LV      VG      Attr       LSize  Pool Origin Data%  Meta%  Move Log Cpy%Sync Convert\n  lv_dane vg_dane -wi-a----- 10.00g`,
        'lvdisplay': () => `  --- Logical volume ---\n  LV Path                /dev/vg_dane/lv_dane\n  LV Name                lv_dane\n  VG Name                vg_dane\n  LV Write Access        read/write\n  LV Status              available\n  LV Size                10.00 GiB`,

        // ── RAID Management ──
        'mdadm': (a) => {
            if (a.includes('--create') || a.includes('-C')) {
                const dev = a.find(arg => arg.startsWith('/dev/')) || '/dev/md0';
                return `mdadm: Defaulting to version 1.2 metadata\nmdadm: array ${dev} started.`;
            }
            if (a.includes('--detail') || a.includes('-D')) {
                return `/dev/md0:\n           Version : 1.2\n        Raid Level : raid1\n        Array Size : 20955136 (19.98 GiB)\n      Raid Devices : 2\n     Total Devices : 2\n             State : clean \n    Active Devices : 2\n    Working Devices: 2\n    Number   Major   Minor   RaidDevice State\n       0       8       16        0      active sync   /dev/sdb\n       1       8       32        1      active sync   /dev/sdc`;
            }
            return 'mdadm: manage MD devices (software RAID)\nUsage: mdadm --create /dev/mdX --level=1 --raid-devices=N /dev/sd...';
        },

        // ── Services & Server Management ──
        'systemctl': (a, term) => {
            const action = a[0]?.toLowerCase();
            const service = a[1]?.toLowerCase().replace(/\.service$/, '') || 'apache2';
            const svcs = term.net.state.services;

            if (action === 'status') {
                const s = svcs[service];
                const active = s && (s.status === 'RUNNING' || s.status.includes('RUNNING'));
                return `● ${service}.service - ${s?.name || service}\n   Loaded: loaded (/lib/systemd/system/${service}.service; ${s?.enabled ? 'enabled' : 'disabled'})\n   Active: ${active ? 'active (running)' : 'inactive (dead)'} since Mon 2026-08-17 08:00:00 UTC\n   Main PID: ${active ? '1420' : '0'} (${service})\n   Tasks: 4 (limit: 4915)\n   Memory: 24.5M\n`;
            }
            if (action === 'start' || action === 'restart' || action === 'reload') {
                if (svcs[service]) svcs[service].status = 'RUNNING';
                term.net.save();
                return '';
            }
            if (action === 'stop') {
                if (svcs[service]) svcs[service].status = 'STOPPED';
                term.net.save();
                return '';
            }
            if (action === 'enable') {
                if (svcs[service]) svcs[service].enabled = true;
                term.net.save();
                return `Created symlink /etc/systemd/system/multi-user.target.wants/${service}.service → /lib/systemd/system/${service}.service.`;
            }
            if (action === 'disable') {
                if (svcs[service]) svcs[service].enabled = false;
                term.net.save();
                return `Removed /etc/systemd/system/multi-user.target.wants/${service}.service.`;
            }
            if (action === 'is-active') {
                const s = svcs[service];
                return (s && s.status === 'RUNNING') ? 'active' : 'inactive';
            }
            return 'systemctl: command executed.';
        },

        'service': (a, term) => {
            const srv = a[0];
            const act = a[1];
            return LINUX_COMMANDS.systemctl([act, srv], term);
        },

        'journalctl': (a) => {
            const unitIdx = a.indexOf('-u');
            const unit = unitIdx !== -1 ? a[unitIdx + 1] : 'system';
            return `-- Journal begins at Mon 2026-08-17 00:00:00 UTC --\nAug 17 08:00:01 zsem-lab systemd[1]: Starting ${unit}...\nAug 17 08:00:02 zsem-lab ${unit}[1420]: Configuration syntax OK.\nAug 17 08:00:03 zsem-lab systemd[1]: Started ${unit}.\n`;
        },

        'a2ensite': (a, term) => {
            const site = a[0] || 'zsem.conf';
            const siteName = site.endsWith('.conf') ? site : site + '.conf';
            term.vfs.createFile(`/etc/apache2/sites-enabled/${siteName}`, '', false);
            return `Enabling site ${siteName}.\nTo activate the new configuration, you need to run:\n  systemctl reload apache2`;
        },

        'a2dissite': (a, term) => {
            const site = a[0] || '000-default.conf';
            term.vfs.removeNode(`/etc/apache2/sites-enabled/${site}`, false, false);
            return `Site ${site} disabled.\nTo activate the new configuration, you need to run:\n  systemctl reload apache2`;
        },

        'a2enmod': (a) => `Enabling module ${a[0] || 'rewrite'}.\nTo activate the new configuration, you need to run:\n  systemctl restart apache2`,
        'a2dismod': (a) => `Module ${a[0] || 'rewrite'} disabled.\nTo activate the new configuration, you need to run:\n  systemctl restart apache2`,

        'apachectl': (a, term) => {
            if (a.includes('configtest') || a.includes('-t')) {
                const conf = term.vfs.getNode('/etc/apache2/apache2.conf', false);
                return conf ? 'Syntax OK' : 'Syntax error on line 1: Configuration file missing';
            }
            return 'apachectl: action performed.';
        },
        'apache2ctl': (a, term) => LINUX_COMMANDS.apachectl(a, term),

        'named-checkconf': (a, term) => {
            const conf = term.vfs.getNode('/etc/bind/named.conf', false);
            return conf ? '' : '/etc/bind/named.conf: open: file not found';
        },

        'named-checkzone': (a, term) => {
            const zone = a[0] || 'zsem.local';
            const file = a[1] || '/etc/bind/db.zsem.local';
            const node = term.vfs.getNode(file, false);
            if (node && node.content && node.content.includes('SOA')) {
                return `zone ${zone}/IN: loaded serial 2\nOK`;
            }
            return `zone ${zone}/IN: NS record missing or file not found\nFAILED`;
        },

        'testparm': (a, term) => {
            const smb = term.vfs.getNode('/etc/samba/smb.conf', false);
            if (smb) {
                return `Load smb config files from /etc/samba/smb.conf\nProcessing section "[egzamin]"\nLoaded services file OK.\nServer role: ROLE_STANDALONE`;
            }
            return 'testparm: error loading /etc/samba/smb.conf';
        },

        'smbpasswd': (a) => `Added user ${a.find(arg => !arg.startsWith('-')) || 'student'} to Samba password database.\n`,
        'pdbedit': (a) => `student:1000:ZSEM Student\n`,
        'dhcpd': (a) => a.includes('-t') ? 'Internet Systems Consortium DHCP Server 4.4.1\nConfiguration file /etc/dhcp/dhcpd.conf syntax test OK.\n' : 'dhcpd started.\n',
        'exportfs': () => '/srv/nfs/dane\t192.168.1.0/24\n',
        'showmount': () => 'Hosts on 192.168.1.100:\n/srv/nfs/dane 192.168.1.0/24\n',
        'postconf': () => 'myhostname = mail.zsem.local\n',

        // ── Networking & Security ──
        'ip': (a, term) => {
            const sub = a[0]?.toLowerCase();
            const net = term.net.state;

            if (sub === 'a' || sub === 'addr' || sub === 'address' || !a.length) {
                return `1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 qdisc noqueue state UNKNOWN\n    inet 127.0.0.1/8 scope host lo\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc pfifo_fast state UP\n    link/ether ${net.mac} brd ff:ff:ff:ff:ff:ff\n    inet ${net.ip}/${net.cidr || '24'} brd 192.168.1.255 scope global eth0`;
            }
            if (sub === 'route' || sub === 'r') {
                if (a[1] === 'add') {
                    if (a.includes('default')) {
                        const viaIdx = a.indexOf('via');
                        if (viaIdx !== -1 && a[viaIdx + 1]) net.gateway = a[viaIdx + 1];
                    }
                    term.net.save();
                    return '';
                }
                return `default via ${net.gateway} dev eth0 proto static\n192.168.1.0/24 dev eth0 proto kernel scope link src ${net.ip}`;
            }
            if (sub === 'link') {
                return `1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 state UNKNOWN\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 state UP`;
            }
            if (sub === 'addr' && a[1] === 'add') {
                const ipArg = a[2];
                if (ipArg && ipArg.includes('/')) {
                    const parts = ipArg.split('/');
                    net.ip = parts[0];
                    net.cidr = parts[1];
                    net.dhcp = false;
                    term.net.save();
                    return '';
                }
            }
            if (sub === 'neigh' || sub === 'neighbor') {
                return `192.168.1.1 dev eth0 lladdr 00:50:56:c0:00:01 REACHABLE\n192.168.1.254 dev eth0 lladdr 00:50:56:c0:00:fe STALE`;
            }
            return 'ip command executed.';
        },

        'ifconfig': (a, term) => {
            const net = term.net.state;
            if (a.length >= 2 && a[0] === 'eth0') {
                net.ip = a[1];
                const maskIdx = a.indexOf('netmask');
                if (maskIdx !== -1 && a[maskIdx + 1]) net.netmask = a[maskIdx + 1];
                net.dhcp = false;
                term.net.save();
                return '';
            }
            return `eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500\n        inet ${net.ip}  netmask ${net.netmask}  broadcast 192.168.1.255\n        ether ${net.mac}  txqueuelen 1000  (Ethernet)\n\nlo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536\n        inet 127.0.0.1  netmask 255.0.0.0\n`;
        },

        'route': (a, term) => {
            const net = term.net.state;
            if (a.includes('add')) {
                const gwIdx = a.indexOf('gw');
                if (gwIdx !== -1 && a[gwIdx + 1]) {
                    net.gateway = a[gwIdx + 1];
                    term.net.save();
                }
                return '';
            }
            return `Kernel IP routing table\nDestination     Gateway         Genmask         Flags Metric Ref    Use Iface\n0.0.0.0         ${net.gateway.padEnd(15)} 0.0.0.0         UG    100    0        0 eth0\n192.168.1.0     0.0.0.0         255.255.255.0   U     100    0        0 eth0\n`;
        },

        'arp': (a) => `Address                  HWtype  HWaddress           Flags Mask            Iface\n192.168.1.1              ether   00:50:56:c0:00:01   C                     eth0\n192.168.1.254            ether   00:50:56:c0:00:fe   C                     eth0\n`,

        'ss': () => `State      Recv-Q Send-Q Local Address:Port  Peer Address:Port Process\nLISTEN     0      128          0.0.0.0:22         0.0.0.0:*     users:(("sshd",pid=1240,fd=3))\nLISTEN     0      511          0.0.0.0:80         0.0.0.0:*     users:(("apache2",pid=1420,fd=4))\nLISTEN     0      128        127.0.0.1:53         0.0.0.0:*     users:(("named",pid=1180,fd=21))\nLISTEN     0      50           0.0.0.0:445        0.0.0.0:*     users:(("smbd",pid=1350,fd=37))\nLISTEN     0      80          0.0.0.0:3306        0.0.0.0:*     users:(("mysqld",pid=1510,fd=28))\n`,
        'netstat': (a) => LINUX_COMMANDS.ss(a),

        'ping': (a) => {
            const host = a.find(arg => !arg.startsWith('-') && !/^\d+$/.test(arg)) || '8.8.8.8';
            let out = `PING ${host} (${host}) 56(84) bytes of data.\n`;
            for (let i = 1; i <= 4; i++) {
                const ms = 12 + Math.floor(Math.random() * 8);
                out += `64 bytes from ${host}: icmp_seq=${i} ttl=117 time=${ms}.${Math.floor(Math.random() * 9)} ms\n`;
            }
            out += `--- ${host} ping statistics ---\n4 packets transmitted, 4 received, 0% packet loss, time 3004ms\n`;
            return out;
        },

        'traceroute': (a) => {
            const host = a.find(arg => !arg.startsWith('-')) || 'google.pl';
            return `traceroute to ${host} (142.250.203.195), 30 hops max, 60 byte packets\n 1  gateway (192.168.1.1)  0.642 ms  0.518 ms  0.490 ms\n 2  10.100.0.1 (10.100.0.1)  4.120 ms  4.089 ms  4.110 ms\n 3  isp-core-01.net.pl (195.114.0.1)  8.432 ms  8.320 ms  8.401 ms\n 4  ${host} (142.250.203.195)  12.180 ms  12.090 ms  12.140 ms`;
        },
        'mtr': (a) => LINUX_COMMANDS.traceroute(a),

        'nslookup': (a, term) => {
            if (!a.length) {
                term.currentSubShell = 'nslookup';
                term.subShellEngine = new NslookupShell(term);
                return `Default Server:  UnKnown\r\nAddress:  ${term.net.state.dns[0] || '8.8.8.8'}\r\n> `;
            }
            const host = a[0];
            let ip = '142.250.187.195';
            if (host.includes('zsem.local')) ip = '192.168.1.100';
            else if (host.includes('localhost')) ip = '127.0.0.1';
            return `Server:\t\t192.168.1.1\nAddress:\t192.168.1.1#53\n\nNon-authoritative answer:\nName:\t${host}\nAddress: ${ip}\n`;
        },

        'dig': (a) => `;\n; <<>> DiG 9.18.18 <<>> ${a[0] || 'google.pl'}\n;; Got answer:\n;; ->>HEADER<<- opcode: QUERY, status: NOERROR, id: 48214\n;; flags: qr rd ra; QUERY: 1, ANSWER: 1, AUTHORITY: 0, ADDITIONAL: 1\n\n;; ANSWER SECTION:\n${a[0] || 'google.pl'}.\t\t300\tIN\tA\t142.250.187.195\n\n;; Query time: 14 msec\n;; SERVER: 192.168.1.1#53(192.168.1.1)\n`,

        'curl': (a) => {
            if (a.includes('-I') || a.includes('-i')) {
                return 'HTTP/1.1 200 OK\nDate: Mon, 17 Aug 2026 08:30:00 GMT\nServer: Apache/2.4.52 (Ubuntu)\nContent-Type: text/html; charset=UTF-8\n';
            }
            return '<!DOCTYPE html>\n<html><head><title>ZSEM Server</title></head><body><h1>Serwer Apache2/Nginx dziala poprawnie!</h1></body></html>\n';
        },

        'wget': (a, term) => {
            const url = a.find(arg => !arg.startsWith('-')) || 'http://example.com/index.html';
            const filename = url.split('/').pop() || 'index.html';
            term.vfs.createFile(filename, '<!DOCTYPE html>\n<html><body>Downloaded content</body></html>\n', false);
            return `--2026-08-17 08:30:00--  ${url}\nResolving server... 192.168.1.100\nConnecting to server... connected.\nHTTP request sent, awaiting response... 200 OK\nLength: 58 [text/html]\nSaving to: '${filename}'\n\n${filename}          100%[===================>]      58  --.-KB/s    in 0s\n\n2026-08-17 08:30:00 (4.2 MB/s) - '${filename}' saved [58/58]\n`;
        },

        'nc': () => 'Connection to 192.168.1.100 80 port [tcp/http] succeeded!\n',
        'netcat': (a) => LINUX_COMMANDS.nc(a),

        'nmap': () => `Starting Nmap 7.93 ( https://nmap.org ) at 2026-08-17 08:30 UTC\nNmap scan report for 192.168.1.100\nHost is up (0.00045s latency).\nNot shown: 994 closed tcp ports (reset)\nPORT     STATE SERVICE     VERSION\n22/tcp   open  ssh         OpenSSH 8.9p1 (Ubuntu)\n25/tcp   open  smtp        Postfix smtpd\n53/tcp   open  domain      BIND 9.18.18\n80/tcp   open  http        Apache httpd 2.4.52\n445/tcp  open  netbios-ssn Samba smbd 4.15.13\n3306/tcp open  mysql       MySQL 8.0.34\n\nService detection performed. Please report any incorrect results at https://nmap.org/submit/ .\nNmap done: 1 IP address (1 host up) scanned in 1.42 seconds\n`,

        'iptables': (a) => {
            if (a.includes('-L') || a.includes('-nL') || a.includes('-S')) {
                return `Chain INPUT (policy ACCEPT)\ntarget     prot opt source               destination\nDROP       tcp  --  0.0.0.0/0            0.0.0.0/0            tcp dpt:8080\nACCEPT     tcp  --  0.0.0.0/0            0.0.0.0/0            tcp dpt:22\nACCEPT     tcp  --  0.0.0.0/0            0.0.0.0/0            tcp dpt:80\n\nChain FORWARD (policy ACCEPT)\ntarget     prot opt source               destination\n\nChain OUTPUT (policy ACCEPT)\ntarget     prot opt source               destination\n`;
            }
            return '';
        },

        'ufw': (a, term) => {
            const sub = a[0]?.toLowerCase();
            const fw = term.net.state.firewallLinux;
            if (sub === 'status') {
                return `Status: ${fw.ufwEnabled ? 'active' : 'inactive'}\n\nTo                         Action      From\n--                         ------      ----\n22/tcp                     ALLOW       Anywhere\n80/tcp                     ALLOW       Anywhere\n`;
            }
            if (sub === 'enable') {
                fw.ufwEnabled = true;
                term.net.save();
                return 'Firewall is active and enabled on system startup\n';
            }
            if (sub === 'disable') {
                fw.ufwEnabled = false;
                term.net.save();
                return 'Firewall stopped and disabled on system startup\n';
            }
            if (sub === 'allow') return `Rule added\nRule added (v6)\n`;
            if (sub === 'deny') return `Rule added\nRule added (v6)\n`;
            return 'ufw: command executed.\n';
        },

        'openssl': () => `Generating a RSA private key\n................................................................+++++\nwriting new private key to '/etc/ssl/private/server.key'\n-----\nCertificate generated successfully.\n`,
        'fail2ban-client': (a) => {
            if (a.includes('status')) {
                return `Status for the jail: sshd\n|- Filter\n|  |- Currently failed: 2\n|  |- Total failed:     18\n|  \`- File list:        /var/log/auth.log\n\`- Actions\n   |- Currently banned: 1\n   |- Total banned:     3\n   \`- Banned IP list:   198.51.100.44\n`;
            }
            if (a.includes('banip') || a.includes('set')) {
                return '26738: [sshd] Ban 198.51.100.44\n';
            }
            return 'fail2ban-client: Fail2ban CLI control interface\n';
        },

        // ── Package Management ──
        'apt': (a, term) => {
            const sub = a[0]?.toLowerCase();
            const pkg = a[1]?.toLowerCase();
            if (sub === 'update') return `Hit:1 http://archive.ubuntu.com/ubuntu jammy InRelease\nGet:2 http://archive.ubuntu.com/ubuntu jammy-updates InRelease [115 kB]\nReading package lists... Done\nBuilding dependency tree... Done\nAll packages are up to date.\n`;
            if (sub === 'install') {
                if (pkg && !term.net.state.installedPackages.includes(pkg)) {
                    term.net.state.installedPackages.push(pkg);
                    term.net.state.stats.packagesInstalled = term.net.state.installedPackages.length;
                    term.net.save();
                }
                return `Reading package lists... Done\nBuilding dependency tree... Done\nThe following NEW packages will be installed:\n  ${pkg || 'package'}\n0 upgraded, 1 newly installed, 0 to remove.\nSetting up ${pkg || 'package'} (1.0-1ubuntu1) ...\n`;
            }
            if (sub === 'remove' || sub === 'purge') {
                if (pkg) {
                    term.net.state.installedPackages = term.net.state.installedPackages.filter(p => p !== pkg);
                    term.net.save();
                }
                return `Removing ${pkg || 'package'} ... Done.\n`;
            }
            return 'apt command completed.\n';
        },
        'apt-get': (a, term) => LINUX_COMMANDS.apt(a, term),
        'dpkg': (a) => {
            if (a.includes('-l')) {
                return `Desired=Unknown/Install/Remove/Purge/Hold\n| Status=Not/Inst/Conf-files/Unpacked/halF-conf/Half-inst/trig-aWait/Trig-pend\n|/ Err?=(none)/Reinst-required (Status,Err: uppercase=bad)\n||/ Name           Version      Architecture Description\n+++-==============-============-============-=================================\nii  apache2        2.4.52-1ubun amd64        Apache HTTP Server\nii  bind9          9.18.18-0ubu amd64        Internet Domain Name Server\nii  openssh-server 8.9p1-3ubunt amd64        secure shell (SSH) server`;
            }
            return 'dpkg: database updated successfully.\n';
        },

        // ── Archive & Compression ──
        'tar': (a, term) => {
            if (a.some(arg => arg.includes('c'))) {
                const target = a.find(arg => arg.endsWith('.tar.gz') || arg.endsWith('.tgz') || arg.endsWith('.tar')) || 'archive.tar.gz';
                term.vfs.createFile(target, 'ARCHIVE_BINARY_DATA', false);
                return '';
            }
            if (a.some(arg => arg.includes('t'))) {
                return `home/student/\nhome/student/Desktop/\nhome/student/script.sh\nhome/student/projekty/\n`;
            }
            return '';
        },
        'gzip': (a, term) => {
            const f = a.find(arg => !arg.startsWith('-'));
            if (f) term.vfs.createFile(f + '.gz', 'GZ_DATA', false);
            return '';
        },
        'gunzip': (a, term) => {
            const f = a.find(arg => !arg.startsWith('-'));
            if (f && f.endsWith('.gz')) term.vfs.createFile(f.replace(/\.gz$/, ''), 'RESTORED_DATA', false);
            return '';
        },

        // ── System & Misc ──
        'crontab': (a) => {
            if (a.includes('-l')) {
                return `# Edit this file to introduce tasks to be run by cron.\n0 2 * * * /usr/local/bin/backup_db.sh > /var/log/backup.log 2>&1\n*/15 * * * * /usr/bin/check_services.sh\n`;
            }
            return 'crontab: crontab updated.\n';
        },

        'hostname': (a, term) => {
            if (a[0]) {
                term.net.state.hostname = a[0];
                term.net.save();
                return '';
            }
            return term.net.state.hostname;
        },
        'hostnamectl': (a, term) => {
            if (a[0] === 'set-hostname' && a[1]) {
                term.net.state.hostname = a[1];
                term.net.save();
                return '';
            }
            return `   Static hostname: ${term.net.state.hostname}\n         Icon name: computer-vm\n           Chassis: vm\n        Machine ID: 894210a4e81298dfa2834a1147c1a209\n           Boot ID: 12844e9291af31a89c2012017a21b8c0\n  Operating System: Ubuntu 22.04.3 LTS\n            Kernel: Linux 5.15.0-89-generic\n      Architecture: x86-64`;
        },

        'uname': (a) => a.includes('-a') ? 'Linux zsem-lab 5.15.0-89-generic #99-Ubuntu SMP x86_64 GNU/Linux' : 'Linux',
        'date': () => new Date().toUTCString(),
        'uptime': () => `${new Date().toLocaleTimeString('pl-PL')} up 2 days, 4:12, 1 user, load average: 0.14, 0.08, 0.05`,
        'df': () => `Filesystem      Size  Used Avail Use% Mounted on\ntmpfs           795M  2.4M  793M   1% /run\n/dev/sda2        30G  8.4G   20G  30% /\ntmpfs           3.9G     0  3.9G   0% /dev/shm\n/dev/sda1       512M   53M  459M  11% /boot\n/dev/sdb1        20G   12M   19G   1% /mnt/dane`,
        'free': () => `               total        used        free      shared  buff/cache   available\nMem:         8167384     1924512     4210456       34120     2032416     5921400\nSwap:        2097148           0     2097148`,

        'mysqldump': () => `-- MySQL dump 10.13  Distrib 8.0.34, for Linux (x86_64)\n-- Host: localhost    Database: szkola\n-- Server version 8.0.34\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n-- Dumping data for table \`uczniowie\`\nLOCK TABLES \`uczniowie\` WRITE;\nINSERT INTO \`uczniowie\` VALUES (1,'Jan','Kowalski','4P'),(2,'Anna','Nowak','4P');\nUNLOCK TABLES;\n-- Dump completed on 2026-08-17 08:30:00`,

        // ── Sub-Shells & Helpers ──
        'nano': (a, term) => {
            const file = a.find(arg => !arg.startsWith('-')) || 'nowy_plik.txt';
            term.openNanoEditor(file);
            return '';
        },
        'mysql': (a, term) => {
            term.currentSubShell = 'mysql';
            term.subShellEngine = new MysqlShell();
            return `Welcome to the MySQL monitor.  Commands end with ; or \\g.\nYour MySQL connection id is 42\nServer version: 8.0.34-0ubuntu0.22.04.1 (Ubuntu)\n\nType 'help;' or '\\h' for help. Type '\\c' to clear the current input statement.\n\nmysql> `;
        },
        'python': (a, term) => {
            term.currentSubShell = 'python';
            term.subShellEngine = new PythonShell();
            return `Python 3.10.12 (main, Jun 11 2023, 05:26:28) [GCC 11.4.0] on linux\nType "help", "copyright", "credits" or "license" for more information.\n>>> `;
        },
        'python3': (a, term) => LINUX_COMMANDS.python(a, term),
        'ssh': (a, term) => {
            const target = a.find(arg => !arg.startsWith('-')) || 'student@192.168.1.100';
            const parts = target.split('@');
            const user = parts.length > 1 ? parts[0] : 'student';
            const host = parts.length > 1 ? parts[1] : parts[0];
            term.currentSubShell = 'ssh';
            term.subShellEngine = new SshShell(term, user, host);
            return `Welcome to Ubuntu 22.04.3 LTS (GNU/Linux 5.15.0-89-generic x86_64)\n * Documentation:  https://help.ubuntu.com\n * Management:     https://landscape.canonical.com\nLast login: Mon Aug 17 08:00:00 2026 from 192.168.1.100\n`;
        },

        'help': () => `Dostępne polecenia powłoki GNU/Bash (Linux CKE):\n\n[Pliki & Katalogi]   ls, cd, pwd, mkdir, rmdir, touch, rm, cp, mv, cat, head, tail, find, tree, stat, file\n[Przetwarzanie]      grep, wc, sort, uniq, cut, tr, sed, awk, tee, more, less, diff\n[Uprawnienia & Konta] chmod, chown, chgrp, umask, useradd, adduser, usermod, userdel, groupadd, groupdel, gpasswd, passwd, su, sudo, id, whoami, groups\n[Dyski & Woluminy]   lsblk, fdisk, blkid, parted, mkfs, mkfs.ext4, mount, umount, pvcreate, pvs, vgcreate, vgs, lvcreate, lvs, mdadm\n[Usługi & Serwery]   systemctl, service, journalctl, a2ensite, a2dissite, a2enmod, apachectl, named-checkconf, named-checkzone, testparm, smbpasswd, dhcpd, exportfs, crontab, mysqldump\n[Sieć & Bezpieczeństwo] ip, ifconfig, route, arp, ss, netstat, ping, traceroute, mtr, nslookup, dig, curl, wget, nc, nmap, iptables, ufw, fail2ban-client, openssl\n[Pakiety & Narzędzia] apt, apt-get, dpkg, tar, gzip, gunzip, which, whereis, type, hostname, hostnamectl, uname, date, uptime, df, free, nano, mysql, python3, ssh, man, clear, history\n`,

        'man': (a) => {
            const cmd = a[0];
            return MAN_PAGES[cmd] || `No manual entry for ${cmd || 'command'}. Type 'help' to see all commands.`;
        },

        'clear': () => '__CLEAR__',
        'history': (a, term) => term.commandHistory.map((c, i) => `  ${i + 1}  ${c}`).join('\n')
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 5. WINDOWS COMMANDS ENGINE (CMD / POWERSHELL)
    // ════════════════════════════════════════════════════════════════════════════

    const WINDOWS_COMMANDS = {
        'cls': () => '__CLEAR__',
        'clear': () => '__CLEAR__',

        'cd': (a, term) => {
            const target = a[0] || 'C:\\Users\\Student';
            const norm = term.vfs.normalizePath(target, true);
            const node = term.vfs.getNode(norm, true);
            if (!node || node.type !== 'dir') return `The system cannot find the path specified.\r\n`;
            term.vfs.currentDirWin = norm;
            return '';
        },
        'chdir': (a, term) => WINDOWS_COMMANDS.cd(a, term),

        'dir': (a, term) => {
            const target = a.find(arg => !arg.startsWith('/')) || '.';
            const node = term.vfs.getNode(target, true);
            if (!node || node.type !== 'dir') return `File Not Found\r\n`;

            const items = Object.values(node.children || {});
            let out = `\r\n Volume in drive C has no label.\r\n Volume Serial Number is A894-32FC\r\n\r\n Directory of ${term.vfs.normalizePath(target, true)}\r\n\r\n`;
            out += `17.08.2026  08:00    <DIR>          .\r\n17.08.2026  08:00    <DIR>          ..\r\n`;
            out += items.map(i => {
                const isDir = i.type === 'dir' ? '<DIR>         ' : '      ' + String(i.size || 0).padStart(8);
                return `17.08.2026  08:30    ${isDir} ${i.name}`;
            }).join('\r\n');
            out += `\r\n               ${items.filter(i => i.type === 'file').length} File(s)          ${items.reduce((s, i) => s + (i.size || 0), 0)} bytes\r\n               ${items.filter(i => i.type === 'dir').length + 2} Dir(s)  52,418,920,448 bytes free\r\n`;
            return out;
        },

        'type': (a, term, pipeInput = '') => {
            if (!a.length && pipeInput) return pipeInput + '\r\n';
            if (!a[0]) return 'The syntax of the command is incorrect.\r\n';
            const node = term.vfs.getNode(a[0], true);
            if (!node || node.type !== 'file') return `The system cannot find the file specified.\r\n`;
            return (node.content || '') + '\r\n';
        },

        'md': (a, term) => {
            if (!a[0]) return 'The syntax of the command is incorrect.\r\n';
            term.vfs.createDirectory(a[0], true, true);
            return '';
        },
        'mkdir': (a, term) => WINDOWS_COMMANDS.md(a, term),

        'del': (a, term) => {
            if (!a[0]) return 'The syntax of the command is incorrect.\r\n';
            term.vfs.removeNode(a[0], true, true);
            return '';
        },
        'erase': (a, term) => WINDOWS_COMMANDS.del(a, term),

        'copy': (a, term) => {
            if (a.length < 2) return 'The syntax of the command is incorrect.\r\n';
            term.vfs.copyNode(a[0], a[1], false, true);
            return '        1 file(s) copied.\r\n';
        },
        'xcopy': (a, term) => {
            term.vfs.copyNode(a[0], a[1], true, true);
            return '1 File(s) copied\r\n';
        },
        'robocopy': (a, term) => {
            term.vfs.copyNode(a[0], a[1], true, true);
            return `\r\n-------------------------------------------------------------------------------\r\n   ROBOCOPY     ::     Robust File Copy for Windows                              \r\n-------------------------------------------------------------------------------\r\n  Total    Copied   Skipped  Mismatch    FAILED    Extras\r\n    Dirs :         2         2         0         0         0         0\r\n   Files :         4         4         0         0         0         0\r\n`;
        },

        'move': (a, term) => {
            if (a.length < 2) return 'The syntax of the command is incorrect.\r\n';
            term.vfs.copyNode(a[0], a[1], true, true);
            term.vfs.removeNode(a[0], true, true);
            return '        1 file(s) moved.\r\n';
        },

        'ren': (a, term) => {
            if (a.length < 2) return 'The syntax of the command is incorrect.\r\n';
            const node = term.vfs.getNode(a[0], true);
            if (node) node.name = a[1];
            return '';
        },
        'rename': (a, term) => WINDOWS_COMMANDS.ren(a, term),

        'echo': (a, term, pipeInput = '') => {
            if (!a.length && pipeInput) return pipeInput + '\r\n';
            return a.join(' ') + '\r\n';
        },

        'findstr': (a, term, pipeInput = '') => {
            const ignoreCase = a.includes('/i') || a.includes('/I');
            const invert = a.includes('/v') || a.includes('/V');
            const showLine = a.includes('/n') || a.includes('/N');
            const pattern = a.find(arg => !arg.startsWith('/'))?.replace(/['"]/g, '');
            const file = a.filter(arg => !arg.startsWith('/'))[1];

            if (!pattern) return '';
            let text = pipeInput;
            if (file) {
                const node = term.vfs.getNode(file, true);
                if (node) text = node.content || '';
            }

            const regex = new RegExp(pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), ignoreCase ? 'i' : '');
            return (text || '').split('\n')
                .map((l, i) => ({ l: l.replace(/\r$/, ''), num: i + 1, match: regex.test(l) }))
                .filter(item => invert ? !item.match : item.match)
                .map(item => showLine ? `${item.num}:${item.l}` : item.l)
                .join('\r\n') + '\r\n';
        },

        'find': (a, term, pipeInput = '') => WINDOWS_COMMANDS.findstr(a, term, pipeInput),
        'more': (a, term, pipeInput = '') => WINDOWS_COMMANDS.type(a, term, pipeInput),
        'sort': (a, term, pipeInput = '') => LINUX_COMMANDS.sort(a, term, pipeInput),

        'whoami': (a, term) => `zsem-student\\${term.net.state.currentUserWin}\r\n`,
        'hostname': (a, term) => `${term.net.state.winHostname}\r\n`,
        'ver': () => `\r\nMicrosoft Windows [Version 10.0.19045.3636]\r\n`,

        'set': (a) => {
            if (a[0]) return `\r\n${a.join(' ')}\r\n`;
            return `\r\nALLUSERSPROFILE=C:\\ProgramData\r\nAPPDATA=C:\\Users\\Student\\AppData\\Roaming\r\nCOMPUTERNAME=ZSEM-STUDENT\r\nOS=Windows_NT\r\nPATH=C:\\Windows\\system32;C:\\Windows;C:\\Windows\\System32\\Wbem;C:\\Program Files\\PowerShell\\7\r\nPROCESSOR_ARCHITECTURE=AMD64\r\nPROMPT=$P$G\r\nSYSTEMDRIVE=C:\r\nSYSTEMROOT=C:\\Windows\r\nTEMP=C:\\Users\\Student\\AppData\\Local\\Temp\r\nUSERDOMAIN=ZSEM-STUDENT\r\nUSERNAME=Student\r\nUSERPROFILE=C:\\Users\\Student\r\nWINDIR=C:\\Windows\r\n`;
        },

        'systeminfo': (a, term) => `\r\nHost Name:                 ${term.net.state.winHostname}\r\nOS Name:                   Microsoft Windows 10 Pro\r\nOS Version:                10.0.19045 N/A Build 19045\r\nDomain:                    WORKGROUP\r\nNetwork Card(s):           1 NIC(s) Installed.\r\n                           [01]: Intel(R) PRO/1000 MT Connection Name: Ethernet\r\n                                 IP address(es): ${term.net.state.ip}\r\n`,

        'ipconfig': (a, term) => {
            const net = term.net.state;
            if (a.includes('/all')) {
                return `\r\nWindows IP Configuration\r\n\r\n   Host Name . . . . . . . . . . . . : ${net.winHostname}\r\n   Primary Dns Suffix  . . . . . . . : zsem.local\r\n\r\nEthernet adapter Ethernet:\r\n   Physical Address. . . . . . . . . : ${net.mac.replace(/:/g, '-')}\r\n   DHCP Enabled. . . . . . . . . . . : ${net.dhcp ? 'Yes' : 'No'}\r\n   IPv4 Address. . . . . . . . . . . : ${net.ip}(Preferred)\r\n   Subnet Mask . . . . . . . . . . . : ${net.netmask}\r\n   Default Gateway . . . . . . . . . : ${net.gateway}\r\n   DNS Servers . . . . . . . . . . . : ${net.dns.join('\r\n                                       ')}\r\n`;
            }
            if (a.includes('/flushdns')) return `\r\nWindows IP Configuration\r\n\r\nSuccessfully flushed the DNS Resolver Cache.\r\n`;
            if (a.includes('/release')) return `\r\nWindows IP Configuration\r\n\r\nNo IP address bound to adapter.\r\n`;
            if (a.includes('/renew')) return `\r\nWindows IP Configuration\r\n\r\nEthernet adapter Ethernet:\r\n   IPv4 Address. . . . . . . . . . . : ${net.ip}\r\n   Subnet Mask . . . . . . . . . . . : ${net.netmask}\r\n   Default Gateway . . . . . . . . . : ${net.gateway}\r\n`;
            return `\r\nWindows IP Configuration\r\n\r\nEthernet adapter Ethernet:\r\n   IPv4 Address. . . . . . . . . . . : ${net.ip}\r\n   Subnet Mask . . . . . . . . . . . : ${net.netmask}\r\n   Default Gateway . . . . . . . . . : ${net.gateway}\r\n`;
        },

        'ping': (a) => {
            const host = a.find(arg => !arg.startsWith('-') && !arg.startsWith('/')) || '8.8.8.8';
            let out = `\r\nPinging ${host} with 32 bytes of data:\r\n`;
            for (let i = 1; i <= 4; i++) {
                const ms = 12 + Math.floor(Math.random() * 8);
                out += `Reply from ${host}: bytes=32 time=${ms}ms TTL=117\r\n`;
            }
            return out;
        },

        'tracert': (a) => {
            const host = a.find(arg => !arg.startsWith('-')) || '8.8.8.8';
            return `\r\nTracing route to ${host} [${host}]\r\nover a maximum of 30 hops:\r\n\r\n  1    <1 ms    <1 ms    <1 ms  192.168.1.1\r\n  2     4 ms     4 ms     3 ms  10.100.0.1\r\n  3     9 ms     8 ms     9 ms  ${host}\r\n\r\nTrace complete.\r\n`;
        },
        'pathping': (a) => WINDOWS_COMMANDS.tracert(a),

        'getmac': (a, term) => `\r\nPhysical Address    Transport Name\r\n=================== ==========================================================\r\n${term.net.state.mac.replace(/:/g, '-')}   \\Device\\Tcpip_{8F64B79A-12E4-42D9-8F7C-902315AB56CD}\r\n`,

        'netstat': () => `\r\nActive Connections\r\n  Proto  Local Address          Foreign Address        State           PID\r\n  TCP    0.0.0.0:80             0.0.0.0:0              LISTENING       1450\r\n  TCP    0.0.0.0:135            0.0.0.0:0              LISTENING       840\r\n  TCP    0.0.0.0:445            0.0.0.0:0              LISTENING       4\r\n  TCP    0.0.0.0:3389           0.0.0.0:0              LISTENING       940\r\n  TCP    192.168.1.100:49712    142.250.187.195:443    ESTABLISHED     2140\r\n`,
        'arp': () => `\r\nInterface: 192.168.1.100 --- 0x4\r\n  Internet Address      Physical Address      Type\r\n  192.168.1.1           00-50-56-c0-00-01     dynamic\r\n  192.168.1.255         ff-ff-ff-ff-ff-ff     static\r\n`,

        'route': (a) => {
            const sub = a[0]?.toLowerCase();
            if (sub === 'print' || !a.length) {
                return `\r\n===========================================================================\r\nIPv4 Route Table\r\n===========================================================================\r\nActive Routes:\r\nNetwork Destination        Netmask          Gateway       Interface  Metric\r\n          0.0.0.0          0.0.0.0      192.168.1.1    192.168.1.100     25\r\n        127.0.0.0        255.0.0.0         On-link         127.0.0.1    331\r\n      192.168.1.0    255.255.255.0         On-link     192.168.1.100    281\r\n===========================================================================\r\n`;
            }
            return `\r\n OK!\r\n`;
        },

        'nslookup': (a, term) => {
            if (!a.length) {
                term.currentSubShell = 'nslookup';
                term.subShellEngine = new NslookupShell(term);
                return `Default Server:  UnKnown\r\nAddress:  ${term.net.state.dns[0] || '8.8.8.8'}\r\n> `;
            }
            const host = a[0];
            const server = a[1] || term.net.state.dns[0] || '8.8.8.8';
            let ip = '142.250.187.195';
            if (host.includes('zsem.local')) ip = '192.168.1.50';
            else if (host.includes('localhost')) ip = '127.0.0.1';
            return `\r\nServer:  UnKnown\r\nAddress:  ${server}\r\n\r\nNon-authoritative answer:\r\nName:    ${host}\r\nAddress:  ${ip}\r\n`;
        },

        'attrib': (a) => a.length ? '' : `A            C:\\Users\\Student\\desktop.ini\r\nA            C:\\Users\\Student\\notes.txt\r\n`,
        'tree': () => `Folder PATH listing for volume Windows\r\nVolume serial number is A894-32FC\r\nC:.\r\n├───Documents\r\n├───Downloads\r\n└───Desktop\r\n`,
        'icacls': (a) => `\r\n${a[0] || 'C:\\Dane'} NT AUTHORITY\\SYSTEM:(I)(OI)(CI)(F)\r\n               BUILTIN\\Administrators:(I)(OI)(CI)(F)\r\n               BUILTIN\\Users:(I)(OI)(CI)(RX)\r\nSuccessfully processed 1 files; Failed processing 0 files\r\n`,
        'cipher': (a) => `\r\n Listing C:\\Dane\\\r\n New files added to this directory will not be encrypted.\r\n\r\nU dane.txt\r\nU raport.docx\r\n`,
        'assoc': (a) => a[0] ? `${a[0]}=txtfile\r\n` : `.bat=batfile\r\n.cmd=cmdfile\r\n.docx=Word.Document.12\r\n.exe=exefile\r\n.ps1=Microsoft.PowerShellScript.1\r\n.txt=txtfile\r\n`,
        'ftype': (a) => a[0] ? `${a[0]}=%SystemRoot%\\system32\\NOTEPAD.EXE %1\r\n` : `txtfile=%SystemRoot%\\system32\\NOTEPAD.EXE %1\r\n`,

        // ── Windows Service & Net Control ──
        'sc': (a, term) => {
            const action = a[0]?.toLowerCase();
            const service = (a[1] || 'w3svc').toLowerCase();
            const svcs = term.net.state.services;

            if (action === 'query') {
                const s = svcs[service];
                const running = s && s.status === 'RUNNING';
                return `\r\nSERVICE_NAME: ${service}\r\n        TYPE               : 10  WIN32_OWN_PROCESS\r\n        STATE              : ${running ? '4  RUNNING' : '1  STOPPED'}\r\n        WIN32_EXIT_CODE    : 0  (0x0)\r\n        SERVICE_EXIT_CODE  : 0  (0x0)\r\n        CHECKPOINT         : 0x0\r\n        WAIT_HINT          : 0x0\r\n`;
            }
            if (action === 'qc') {
                return `\r\n[SC] QueryServiceConfig SUCCESS\r\n\r\nSERVICE_NAME: ${service}\r\n        TYPE               : 10  WIN32_OWN_PROCESS\r\n        START_TYPE         : 2   AUTO_START\r\n        ERROR_CONTROL      : 1   NORMAL\r\n        BINARY_PATH_NAME   : C:\\Windows\\System32\\svchost.exe -k iissvc\r\n        LOAD_ORDER_GROUP   :\r\n        TAG                : 0\r\n        DISPLAY_NAME       : World Wide Web Publishing Service\r\n        DEPENDENCIES       : WAS\r\n        SERVICE_START_NAME : LocalSystem\r\n`;
            }
            if (action === 'start') {
                if (svcs[service]) svcs[service].status = 'RUNNING';
                term.net.save();
                return `\r\nSERVICE_NAME: ${service}\r\n        STATE              : 4  RUNNING\r\n`;
            }
            if (action === 'stop') {
                if (svcs[service]) svcs[service].status = 'STOPPED';
                term.net.save();
                return `\r\nSERVICE_NAME: ${service}\r\n        STATE              : 1  STOPPED\r\n`;
            }
            if (action === 'config') {
                return `\r\n[SC] ChangeServiceConfig SUCCESS\r\n`;
            }
            return `\r\nDESCRIPTION:\r\n        SC is a command line program used for communicating with the NT Service Controller.\r\nUSAGE:\r\n        sc <server> [command] [service name] <option1> <option2>...\r\n`;
        },

        'net': (a, term) => {
            const sub = a[0]?.toLowerCase();
            const svcs = term.net.state.services;

            if (sub === 'start') {
                const srv = (a[1] || 'w3svc').toLowerCase();
                if (svcs[srv]) svcs[srv].status = 'RUNNING';
                term.net.save();
                return `\r\nThe ${a[1] || 'service'} service was started successfully.\r\n`;
            }
            if (sub === 'stop') {
                const srv = (a[1] || 'w3svc').toLowerCase();
                if (svcs[srv]) svcs[srv].status = 'STOPPED';
                term.net.save();
                return `\r\nThe ${a[1] || 'service'} service was stopped successfully.\r\n`;
            }
            if (sub === 'user') {
                if (a[1] && a.includes('/add')) return `\r\nThe command completed successfully.\r\n`;
                if (a[1] && a.includes('/delete')) return `\r\nThe command completed successfully.\r\n`;
                return `\r\nUser accounts for \\\\ZSEM-STUDENT\r\n-------------------------------------------------------------------------------\r\nAdministrator            DefaultAccount           Guest            student\r\nThe command completed successfully.\r\n`;
            }
            if (sub === 'localgroup') return `\r\nAliases for \\\\ZSEM-STUDENT\r\n-------------------------------------------------------------------------------\r\n*Administrators          *Users                   *Remote Desktop Users\r\nThe command completed successfully.\r\n`;
            if (sub === 'share') return `\r\nShare name   Resource                        Remark\r\n-------------------------------------------------------------------------------\r\nC$           C:\\                             Default share\r\nADMIN$       C:\\Windows                      Remote Admin\r\nDane         C:\\Dane\r\nThe command completed successfully.\r\n`;
            if (sub === 'use') return `\r\nStatus       Local     Remote                    Network\r\n-------------------------------------------------------------------------------\r\nOK           Z:        \\\\192.168.1.100\\Dane      Microsoft Windows Network\r\nThe command completed successfully.\r\n`;
            if (sub === 'accounts') return `\r\nForce user logoff how long after time expires?:       Never\r\nMinimum password age (days):                          0\r\nMaximum password age (days):                          42\r\nMinimum password length:                              7\r\nLength of password history maintained:                24\r\nLockout threshold:                                    5\r\nThe command completed successfully.\r\n`;
            return `\r\nNET command executed successfully.\r\n`;
        },

        'netsh': (a, term) => {
            const str = a.join(' ').toLowerCase();
            const net = term.net.state;

            if (str.includes('interface ip set address') || str.includes('int ip set addr')) {
                const staticIdx = a.findIndex(p => p.toLowerCase() === 'static');
                if (staticIdx !== -1 && a[staticIdx + 1]) {
                    net.ip = a[staticIdx + 1];
                    if (a[staticIdx + 2]) net.netmask = a[staticIdx + 2];
                    if (a[staticIdx + 3]) net.gateway = a[staticIdx + 3];
                    net.dhcp = false;
                    term.net.save();
                    return '\r\nOk.\r\n';
                }
            }
            if (str.includes('interface ip set dns') || str.includes('int ip set dns')) {
                const staticIdx = a.findIndex(p => p.toLowerCase() === 'static');
                if (staticIdx !== -1 && a[staticIdx + 1]) {
                    net.dns = [a[staticIdx + 1]];
                    term.net.save();
                    return '\r\nOk.\r\n';
                }
            }
            if (str.includes('advfirewall set allprofiles state off')) {
                net.firewallWin.enabled = false;
                term.net.save();
                return '\r\nOk.\r\n';
            }
            if (str.includes('advfirewall set allprofiles state on')) {
                net.firewallWin.enabled = true;
                term.net.save();
                return '\r\nOk.\r\n';
            }
            if (str.includes('advfirewall show allprofiles')) {
                return `\r\nDomain Profile State: ${net.firewallWin.enabled ? 'ON' : 'OFF'}\r\nPrivate Profile State: ${net.firewallWin.enabled ? 'ON' : 'OFF'}\r\nPublic Profile State: ${net.firewallWin.enabled ? 'ON' : 'OFF'}\r\nOk.\r\n`;
            }
            return '\r\nOk.\r\n';
        },

        'tasklist': () => `\r\nImage Name                     PID Session Name        Session#    Mem Usage\r\n========================= ======== ================ =========== ============\r\nSystem Idle Process              0 Services                   0          8 K\r\nSystem                           4 Services                   0        140 K\r\nsvchost.exe                    840 Services                   0     18,400 K\r\nexplorer.exe                  2140 Console                    1     84,200 K\r\ncmd.exe                       3410 Console                    1      4,820 K\r\nw3wp.exe                      1450 Services                   0     45,000 K\r\n`,
        'taskkill': (a) => `\r\nSUCCESS: Sent termination signal to process with PID ${a.find(arg => /^\d+$/.test(arg)) || '1450'}.\r\n`,
        'driverquery': () => `\r\nModule Name  Display Name           Driver Type   Link Date\r\n============ ====================== ============= ======================\r\ne1i65x64     Intel(R) PRO/1000      Kernel        17.08.2026 08:00:00\r\npartmgr      Partition Driver       Kernel        17.08.2026 08:00:00\r\nvolmgr       Volume Manager Driver  Kernel        17.08.2026 08:00:00\r\n`,
        'shutdown': () => `\r\nThe system is scheduled to shut down.\r\n`,

        'sfc': () => `\r\nBeginning system scan. This process will take some time.\r\nBeginning verification phase of system scan.\r\nVerification 100% complete.\r\nWindows Resource Protection did not find any integrity violations.\r\n`,
        'chkdsk': () => `\r\nThe type of the file system is NTFS.\r\nVolume label is Windows.\r\nWindows has scanned the file system and found no problems.\r\nNo further action is required.\r\n`,
        'dism': () => `\r\nDeployment Image Servicing and Management tool\r\nVersion: 10.0.19041.3636\r\nImage Version: 10.0.19045.3636\r\n[==========================100.0%==========================]\r\nThe restore operation completed successfully.\r\nThe operation completed successfully.\r\n`,
        'gpupdate': () => `\r\nUpdating policy...\r\n\r\nComputer Policy update has completed successfully.\r\nUser Policy update has completed successfully.\r\n`,
        'gpresult': () => `\r\nMicrosoft (R) Windows (R) Operating System Group Policy Result Tool v2.0\r\nUSER SETTINGS\r\n--------------\r\n    Applied Group Policy Objects\r\n    ----------------------------\r\n        Default Domain Policy\r\n        ZSEM School Policy\r\n`,

        'reg': (a) => {
            if (a.includes('query')) return `\r\nHKEY_LOCAL_MACHINE\\Software\\Microsoft\\Windows\\CurrentVersion\r\n    ProgramFilesDir    REG_SZ    C:\\Program Files\r\n    CommonFilesDir     REG_SZ    C:\\Program Files\\Common Files\r\n`;
            return `\r\nThe operation completed successfully.\r\n`;
        },
        'certutil': (a) => {
            const f = a.find(arg => !arg.startsWith('-') && !['md5', 'sha1', 'sha256'].includes(arg.toLowerCase())) || 'plik.txt';
            return `\r\nSHA256 hash of ${f}:\r\n9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08\r\nCertUtil: -hashfile command completed successfully.\r\n`;
        },

        'iisreset': () => `\r\nAttempting stop...\r\nInternet services successfully stopped\r\nAttempting start...\r\nInternet services successfully restarted\r\n`,
        'appcmd': () => `\r\nSITE "Default Web Site" (id:1,bindings:http/*:80:,state:Started)\r\nAPP "Default Web Site/" (applicationPool:DefaultAppPool)\r\n`,
        'dnscmd': () => `\r\nEnumerated zone list:\r\n  Zone name                      Type       Storage         Status\r\n  zsem.local                     Primary    File            Running\r\nCommand completed successfully.\r\n`,
        'dsadd': (a) => `\r\ndsadd succeeded: ${a.join(' ')}\r\n`,
        'chcp': (a) => a[0] ? `Active code page: ${a[0]}\r\n` : `Active code page: 852\r\n`,
        'where': (a) => `C:\\Windows\\System32\\${a[0] || 'cmd'}.exe\r\n`,

        // ── Sub-Shells ──
        'powershell': (a, term) => {
            if (!a.length) {
                term.currentSubShell = 'powershell';
                term.subShellEngine = new PowerShellEngine(term);
                return 'PowerShell 7.3.0\r\nLoading personal and system profiles took 240ms.\r\nPS C:\\Users\\Student> ';
            }
            const engine = new PowerShellEngine(term);
            return engine.execute(a.join(' ')).output;
        },
        'pwsh': (a, term) => WINDOWS_COMMANDS.powershell(a, term),

        'diskpart': (a, term) => {
            term.currentSubShell = 'diskpart';
            term.subShellEngine = new DiskpartShell();
            return '\r\nMicrosoft DiskPart version 10.0.19041.3636\r\nCopyright (C) Microsoft Corporation.\r\nOn computer: ZSEM-STUDENT\r\n\r\nDISKPART> ';
        },

        'help': () => `\r\nDostepne polecenia powloki Windows CMD & PowerShell (CKE):\r\n\r\n[Pliki & Katalogi]   dir, cd, chdir, md, mkdir, rd, del, erase, copy, xcopy, robocopy, move, ren, rename, type, more, tree, attrib, icacls, cipher, assoc, ftype\r\n[Wyszukiwanie]       findstr, find, sort, where\r\n[Siec & Diagnostyka] ipconfig, ping, tracert, pathping, getmac, netstat, arp, route, nslookup, netsh\r\n[Uslugi & System]    sc, net (start, stop, user, localgroup, share, use, accounts), tasklist, taskkill, driverquery, shutdown, sfc, chkdsk, dism, gpupdate, gpresult, reg, certutil, systeminfo, ver, hostname, whoami, set, chcp\r\n[Serwery & AD]       iisreset, appcmd, dnscmd, dsadd\r\n[Powloki & Narzedzia] powershell, pwsh, diskpart, cls, clear, exit\r\n`
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 6. MAN PAGES DATABASE & CKE KNOWLEDGE REPOSITORY
    // ════════════════════════════════════════════════════════════════════════════

    const MAN_PAGES = {
        'ls': 'LS(1) - Wyświetla zawartość katalogu\n\nSKŁADNIA:\n  ls [OPCJE]... [PLIK]...\n\nOPCJE:\n  -a, --all    wyświetla pliki ukryte (zaczynające się od kropki)\n  -l           format długi (uprawnienia, właściciel, rozmiar, data)\n  -h           czytelne jednostki rozmiaru (KB, MB)\n\nWSKAZÓWKA CKE:\n  Częste polecenie egzaminacyjne: `ls -la /etc` w celu sprawdzenia uprawnień.',
        'cd': 'CD(1) - Zmiana bieżącego katalogu roboczego\n\nSKŁADNIA:\n  cd [KATALOG]\n\nPRZYKŁADY:\n  cd ~          przejście do katalogu domowego (/home/student)\n  cd ..         przejście katalog wyżej\n  cd /var/www   ścieżka bezwzględna',
        'chmod': 'CHMOD(1) - Zmiana uprawnień dostępu do plików/katalogów\n\nSKŁADNIA:\n  chmod [OPCJE]... TRYB[,TRYB]... PLIK...\n\nFORMAT NUMERYCZNY:\n  4 = Odczyt (r), 2 = Zapis (w), 1 = Wykonanie (x)\n\nPRZYKŁADY CKE:\n  chmod 750 skrypt.sh   (rwxr-x--- : właściciel pełne, grupa r+x, inni brak)\n  chmod 644 plik.conf   (rw-r--r-- : standardowe dla plików konfiguracyjnych)\n  chmod -R 775 /var/www (rekurencyjnie)',
        'chown': 'CHOWN(1) - Zmiana właściciela i grupy pliku\n\nSKŁADNIA:\n  chown [OPCJE]... WŁAŚCICIEL[:GRUPA] PLIK...\n\nPRZYKŁADY CKE:\n  chown student:www-data /var/www/html -R\n  chown root:shadow /etc/shadow',
        'ps': 'PS(1) - Raportowanie bieżących procesów systemowych\n\nSKŁADNIA:\n  ps aux | ps -ef\n\nPRZYKŁADY:\n  ps aux | grep apache2\n  ps -u student',
        'kill': 'KILL(1) - Wysyłanie sygnałów do procesów (zakańczanie)\n\nSKŁADNIA:\n  kill [-SYGNAŁ] PID...\n\nPRZYKŁADY:\n  kill 1420       (SIGTERM - łagodne zatrzymanie)\n  kill -9 1420    (SIGKILL - natychmiastowe wymuszenie)',
        'grep': 'GREP(1) - Wyszukiwanie wzorców tekstowych\n\nSKŁADNIA:\n  grep [OPCJE]... WZORZEC [PLIK]...\n\nOPCJE:\n  -i    ignorowanie wielkości liter\n  -v    odwrócenie dopasowania (wiersze niezawierające wzorca)\n  -n    wyświetlanie numerów wierszy\n  -c    zliczanie wystąpień',
        'systemctl': 'SYSTEMCTL(1) - Zarządzanie usługami systemd\n\nKOMENDY:\n  start USŁUGA     uruchamia usługę\n  stop USŁUGA      zatrzymuje usługę\n  restart USŁUGA   restartuje usługę po zmianie konfiguracji\n  status USŁUGA    sprawdza stan aktywności i ostatnie błędy\n  enable USŁUGA    włącza autostart przy starcie systemu\n  disable USŁUGA   wyłącza autostart',
        'apache2ctl': 'APACHE2CTL(8) - Narzędzie diagnostyczne serwera Apache2\n\nKOMENDY:\n  configtest, -t   weryfikuje poprawność składniową plików w /etc/apache2\n  graceful         przeładowuje konfigurację bez zrywania sesji',
        'iptables': 'IPTABLES(8) - Zapora sieciowa i translacja adresów (NAT)\n\nPRZYKŁADY CKE:\n  iptables -A INPUT -p tcp --dport 80 -j ACCEPT\n  iptables -A INPUT -p tcp --dport 22 -s 192.168.1.0/24 -j ACCEPT\n  iptables -A INPUT -j DROP\n  iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE',
        'ufw': 'UFW(8) - Uncomplicated Firewall dla Ubuntu/Debiana\n\nKOMENDY:\n  ufw enable\n  ufw status verbose\n  ufw allow 80/tcp\n  ufw deny from 192.168.1.50',
        'ip': 'IP(8) - Konfiguracja interfejsów, routingu i tuneli\n\nPRZYKŁADY CKE:\n  ip a, ip addr show dev eth0\n  ip addr add 192.168.1.100/24 dev eth0\n  ip route add default via 192.168.1.1\n  ip link set eth0 up',
        'ifconfig': 'IFCONFIG(8) - Klasyczne narzędzie konfiguracji sieci (net-tools)\n\nPRZYKŁADY:\n  ifconfig eth0 192.168.1.100 netmask 255.255.255.0 up',
        'pvcreate': 'PVCREATE(8) - Inicjalizacja dysków fizycznych dla LVM\n\nSKŁADNIA:\n  pvcreate /dev/sdb1 /dev/sdc1',
        'vgcreate': 'VGCREATE(8) - Tworzenie grupy woluminów LVM\n\nSKŁADNIA:\n  vgcreate vg_dane /dev/sdb1',
        'lvcreate': 'LVCREATE(8) - Tworzenie woluminu logicznego LVM\n\nSKŁADNIA:\n  lvcreate -n lv_dane -L 10G vg_dane',
        'mdadm': 'MDADM(8) - Zarządzanie macierzami dyskowymi RAID w systemie Linux\n\nPRZYKŁADY CKE:\n  mdadm --create /dev/md0 --level=1 --raid-devices=2 /dev/sdb /dev/sdc\n  cat /proc/mdstat\n  mdadm --detail /dev/md0',
        'fail2ban-client': 'FAIL2BAN-CLIENT(1) - Zarządzanie demonem blokowania brute-force\n\nKOMENDY:\n  fail2ban-client status sshd\n  fail2ban-client set sshd banip 198.51.100.44\n  fail2ban-client set sshd unbanip 198.51.100.44',
        'mysqldump': 'MYSQLDUMP(1) - Eksport bazy danych do pliku SQL\n\nSKŁADNIA:\n  mysqldump -u root -p nazwa_bazy > kopia.sql\n\nODTWORZENIE:\n  mysql -u root -p nowa_baza < kopia.sql',
        'traceroute': 'TRACEROUTE(8) - Śledzenie trasy pakietów IP do hosta docelowego\n\nSKŁADNIA:\n  traceroute google.pl   (Windows: tracert google.pl)',
        'tar': 'TAR(1) - Archiwizacja i kompresja plików\n\nPRZYKŁADY:\n  tar -czvf backup.tar.gz /var/www\n  tar -xzvf backup.tar.gz -C /opt',
        'sc': 'SC(1) - Service Control - Zarządzanie usługami Windows\n\nPRZYKŁADY:\n  sc query w3svc\n  sc start w3svc\n  sc stop w3svc\n  sc config w3svc start= auto',
        'diskpart': 'DISKPART(8) - Zarządzanie dyskami, partycjami i woluminami Windows\n\nPRZYKŁADY:\n  list disk -> select disk 1 -> clean -> create partition primary -> format fs=ntfs quick -> assign letter=E',
        'help': 'HELP - Wyświetla zwięzłe podsumowanie dostępnych poleceń'
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 7. 35 MULTI-STEP CKE EXAM SCENARIOS (INF.02, INF.03, INF.08)
    // ════════════════════════════════════════════════════════════════════════════

    const CKE_SCENARIOS = [
        {
            id: 'inf02_ip_diag',
            title: 'Pełna diagnostyka interfejsu sieciowego',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★☆☆',
            xp: 25,
            os: 'any',
            desc: 'Przeprowadź pełną diagnostykę sieciową: odczytaj konfigurację IP/MAC, sprawdź łączność z bramą i rozwiąż nazwę DNS.',
            steps: [
                {
                    task: 'Wyświetl pełną konfigurację interfejsów sieciowych (Linux: ifconfig / ip a | Windows: ipconfig /all)',
                    ckeDesc: 'Zadanie weryfikuje umiejętność odczytu adresu IPv4, maski podsieci oraz adresu fizycznego karty sieciowej (MAC).',
                    syntaxHint: 'ifconfig   LUB   ip a   LUB   ipconfig /all',
                    hint: 'W systemie Linux użyj polecenia `ifconfig` lub `ip a`. W Windows wpisz `ipconfig /all`.',
                    validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a|ip\s+addr)/i.test(cmd) : /^ipconfig\s+\/all/i.test(cmd)
                },
                {
                    task: 'Wyślij zapytanie kontrolne ping do bramy domyślnej 192.168.1.1',
                    ckeDesc: 'Test łączności warstwy 3 (ICMP) z routerem / bramą domyślną podsieci lokalnej.',
                    syntaxHint: 'ping 192.168.1.1',
                    hint: 'Wpisz `ping 192.168.1.1` aby sprawdzić czas odpowiedzi (RTT) i brak utraty pakietów.',
                    validate: (cmd) => /^ping\s+.*192\.168\.1\.1/i.test(cmd)
                },
                {
                    task: 'Rozwiąż nazwę domeny google.pl za pomocą nslookup',
                    ckeDesc: 'Sprawdzenie poprawności konfiguracji serwerów DNS w systemie.',
                    syntaxHint: 'nslookup google.pl',
                    hint: 'Wpisz `nslookup google.pl` aby odpytać skonfigurowany serwer nazw.',
                    validate: (cmd) => /^nslookup\s+google\.pl/i.test(cmd)
                },
                {
                    task: 'Wykonaj śledzenie trasy pakietów do hosta google.pl (traceroute / tracert)',
                    ckeDesc: 'Analiza węzłów pośrednich (routerów) na trasie pakietu IP.',
                    syntaxHint: 'traceroute google.pl   LUB   tracert google.pl',
                    hint: 'W systemie Linux użyj `traceroute google.pl`, w Windows `tracert google.pl`.',
                    validate: (cmd) => /^(traceroute|tracert)\s+google\.pl/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_static_ip',
            title: 'Konfiguracja statycznego IP i bramy',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            xp: 30,
            os: 'any',
            desc: 'Skonfiguruj statyczny adres IPv4: 10.0.0.50/24 z bramą domyślną 10.0.0.1.',
            steps: [
                {
                    task: 'Wyświetl bieżący adres IP interfejsu',
                    ckeDesc: 'Odczyt wyjściowego stanu adresacji przed modyfikacją.',
                    syntaxHint: 'ip a   LUB   ipconfig',
                    hint: 'Użyj `ip a` lub `ipconfig`.',
                    validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a)/i.test(cmd) : /^ipconfig/i.test(cmd)
                },
                {
                    task: 'Ustaw adres IP 10.0.0.50 (Linux: ip addr add 10.0.0.50/24 dev eth0 | Win: netsh interface ip set address "Ethernet" static 10.0.0.50 255.255.255.0 10.0.0.1)',
                    ckeDesc: 'Przypisanie statycznego adresu IP i maski podsieci.',
                    syntaxHint: 'ip addr add 10.0.0.50/24 dev eth0   LUB   netsh interface ip set address "Ethernet" static 10.0.0.50 255.255.255.0 10.0.0.1',
                    hint: 'W Linux: `ip addr add 10.0.0.50/24 dev eth0`. W Windows: `netsh interface ip set address "Ethernet" static 10.0.0.50 255.255.255.0 10.0.0.1`.',
                    validate: (cmd, os, vfs, net) => net.state.ip === '10.0.0.50' || /(10\.0\.0\.50)/i.test(cmd)
                },
                {
                    task: 'Ustaw domyślną bramę 10.0.0.1 (Linux: ip route add default via 10.0.0.1 | Win: netsh...)',
                    ckeDesc: 'Konfiguracja wpisu domyślnego w tablicy routingu.',
                    syntaxHint: 'ip route add default via 10.0.0.1',
                    hint: 'Wpisz `ip route add default via 10.0.0.1`.',
                    validate: (cmd, os, vfs, net) => net.state.gateway === '10.0.0.1' || /(10\.0\.0\.1)/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj wprowadzone zmiany poleceniem ifconfig lub ipconfig',
                    ckeDesc: 'Weryfikacja poprawności nowego adresu i bramy.',
                    syntaxHint: 'ifconfig   LUB   ipconfig',
                    hint: 'Wpisz `ifconfig` lub `ipconfig`.',
                    validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a)/i.test(cmd) : /^ipconfig/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_apache_vhost',
            title: 'Instalacja i konfiguracja VirtualHost Apache2',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            xp: 40,
            os: 'linux',
            desc: 'Zainstaluj serwer WWW Apache2, utwórz i aktywuj wirtualnego hosta zsem.conf, przetestuj składnię i zrestartuj usługę.',
            steps: [
                {
                    task: 'Zainstaluj pakiet apache2 poleceniem: apt install apache2',
                    ckeDesc: 'Instalacja serwera HTTP Apache2 z repozytorium APT.',
                    syntaxHint: 'apt install apache2',
                    hint: 'Użyj menedżera pakietów: `apt install apache2`.',
                    validate: (cmd) => /^apt(-get)?\s+install\s+apache2/i.test(cmd)
                },
                {
                    task: 'Otwórz do edycji plik wirtualnego hosta: nano /etc/apache2/sites-available/zsem.conf',
                    ckeDesc: 'Konfiguracja dyrektyw ServerName i DocumentRoot w pliku .conf.',
                    syntaxHint: 'nano /etc/apache2/sites-available/zsem.conf',
                    hint: 'Użyj wbudowanego edytora: `nano /etc/apache2/sites-available/zsem.conf`.',
                    validate: (cmd) => /^nano\s+.*zsem\.conf/i.test(cmd)
                },
                {
                    task: 'Aktywuj witrynę za pomocą narzędzia a2ensite zsem.conf',
                    ckeDesc: 'Tworzenie dowiązania symbolicznego w katalogu sites-enabled.',
                    syntaxHint: 'a2ensite zsem.conf',
                    hint: 'Wpisz `a2ensite zsem.conf` aby włączyć vhosta.',
                    validate: (cmd) => /^a2ensite\s+zsem(\.conf)?/i.test(cmd)
                },
                {
                    task: 'Przetestuj poprawność składni plików konfiguracyjnych Apache (apachectl configtest)',
                    ckeDesc: 'Kontrola błędów składniowych przed restartem demona.',
                    syntaxHint: 'apachectl configtest   LUB   apache2ctl -t',
                    hint: 'Wpisz `apachectl configtest` lub `apache2ctl -t`.',
                    validate: (cmd) => /^apache(2)?ctl\s+(configtest|-t)/i.test(cmd)
                },
                {
                    task: 'Zrestartuj usługę Apache2 (systemctl restart apache2)',
                    ckeDesc: 'Przeładowanie procesu nadrzędnego Apache.',
                    syntaxHint: 'systemctl restart apache2',
                    hint: 'Wpisz `systemctl restart apache2`.',
                    validate: (cmd) => /^systemctl\s+(restart|start|reload)\s+apache2/i.test(cmd)
                },
                {
                    task: 'Sprawdź odpowiedź serwera HTTP poleceniem: curl http://localhost',
                    ckeDesc: 'Weryfikacja kodu odpowiedzi 200 OK.',
                    syntaxHint: 'curl http://localhost',
                    hint: 'Wpisz `curl http://localhost` lub `curl -I http://localhost`.',
                    validate: (cmd) => /^curl\s+.*localhost/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_dns_zone',
            title: 'Konfiguracja strefy domeny w BIND9 DNS',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            xp: 40,
            os: 'linux',
            desc: 'Zainstaluj BIND9, skonfiguruj strefę forward zsem.local, sprawdź składnię i przetestuj zapytaniem nslookup.',
            steps: [
                {
                    task: 'Zainstaluj serwer DNS BIND9: apt install bind9',
                    ckeDesc: 'Instalacja oprogramowania serwera nazw BIND9.',
                    syntaxHint: 'apt install bind9',
                    hint: 'Wpisz `apt install bind9`.',
                    validate: (cmd) => /^apt(-get)?\s+install\s+bind9/i.test(cmd)
                },
                {
                    task: 'Edytuj plik deklaracji stref: nano /etc/bind/named.conf.local',
                    ckeDesc: 'Definicja strefy forward typu master w named.conf.local.',
                    syntaxHint: 'nano /etc/bind/named.conf.local',
                    hint: 'Wpisz `nano /etc/bind/named.conf.local`.',
                    validate: (cmd) => /^nano\s+.*named\.conf\.local/i.test(cmd)
                },
                {
                    task: 'Sprawdź poprawność pliku głównego: named-checkconf',
                    ckeDesc: 'Narzędzie weryfikacji składni konfiguracji BIND9.',
                    syntaxHint: 'named-checkconf',
                    hint: 'Wpisz `named-checkconf`.',
                    validate: (cmd) => /^named-checkconf/i.test(cmd)
                },
                {
                    task: 'Sprawdź poprawność rekordu SOA strefy: named-checkzone zsem.local /etc/bind/db.zsem.local',
                    ckeDesc: 'Weryfikacja numeru seryjnego i rekordów NS/A strefy.',
                    syntaxHint: 'named-checkzone zsem.local /etc/bind/db.zsem.local',
                    hint: 'Wpisz `named-checkzone zsem.local /etc/bind/db.zsem.local`.',
                    validate: (cmd) => /^named-checkzone\s+zsem\.local/i.test(cmd)
                },
                {
                    task: 'Uruchom serwer DNS: systemctl restart bind9',
                    ckeDesc: 'Uruchomienie demona named.',
                    syntaxHint: 'systemctl restart bind9',
                    hint: 'Wpisz `systemctl restart bind9`.',
                    validate: (cmd) => /^systemctl\s+(restart|start)\s+bind9/i.test(cmd)
                },
                {
                    task: 'Wykonaj test zapytania DNS: nslookup www.zsem.local 127.0.0.1',
                    ckeDesc: 'Odpytanie lokalnego resolvera BIND9.',
                    syntaxHint: 'nslookup www.zsem.local 127.0.0.1',
                    hint: 'Wpisz `nslookup www.zsem.local 127.0.0.1`.',
                    validate: (cmd) => /^nslookup\s+.*zsem\.local/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_samba_share',
            title: 'Udostępnianie zasobu w sieci przez Sambę',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★☆',
            xp: 35,
            os: 'linux',
            desc: 'Skonfiguruj udział sieciowy [egzamin] w pliku smb.conf, zweryfikuj testparm i utwórz użytkownika Samby.',
            steps: [
                {
                    task: 'Zainstaluj pakiet serwera Samba: apt install samba',
                    ckeDesc: 'Instalacja usługi protokołu SMB/CIFS dla sieci Windows/Linux.',
                    syntaxHint: 'apt install samba',
                    hint: 'Wpisz `apt install samba`.',
                    validate: (cmd) => /^apt(-get)?\s+install\s+samba/i.test(cmd)
                },
                {
                    task: 'Edytuj konfigurację udziałów: nano /etc/samba/smb.conf',
                    ckeDesc: 'Definiowanie sekcji udziału, uprawnień create mask i valid users.',
                    syntaxHint: 'nano /etc/samba/smb.conf',
                    hint: 'Wpisz `nano /etc/samba/smb.conf`.',
                    validate: (cmd) => /^nano\s+.*smb\.conf/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj poprawność konfiguracji narzędziem: testparm',
                    ckeDesc: 'Sprawdzenie parametrów w pliku smb.conf przed uruchomieniem.',
                    syntaxHint: 'testparm',
                    hint: 'Wpisz `testparm`.',
                    validate: (cmd) => /^testparm/i.test(cmd)
                },
                {
                    task: 'Dodaj użytkownika do bazy Samby: smbpasswd -a student',
                    ckeDesc: 'Inicjalizacja hasła NTLM użytkownika w bazie passdb.',
                    syntaxHint: 'smbpasswd -a student',
                    hint: 'Wpisz `smbpasswd -a student`.',
                    validate: (cmd) => /^smbpasswd\s+-a\s+student/i.test(cmd)
                },
                {
                    task: 'Zrestartuj usługę smbd (systemctl restart smbd)',
                    ckeDesc: 'Restart demona Samba smbd.',
                    syntaxHint: 'systemctl restart smbd',
                    hint: 'Wpisz `systemctl restart smbd`.',
                    validate: (cmd) => /^systemctl\s+(restart|start)\s+smbd/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_iptables_drop',
            title: 'Konfiguracja reguł filtrowania w iptables',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★☆',
            xp: 35,
            os: 'linux',
            desc: 'Zabezpiecz serwer regułami zapory: przejrzyj reguły, zablokuj port 8080 i zezwól na ruch HTTPS.',
            steps: [
                {
                    task: 'Wyświetl aktualną listę reguł: iptables -L',
                    ckeDesc: 'Przegląd łańcuchów INPUT, FORWARD i OUTPUT w tabeli filter.',
                    syntaxHint: 'iptables -L',
                    hint: 'Wpisz `iptables -L` lub `iptables -nL`.',
                    validate: (cmd) => /^iptables\s+(-L|-vL|-nL)/i.test(cmd)
                },
                {
                    task: 'Zablokuj przychodzący ruch TCP na porcie 8080: iptables -A INPUT -p tcp --dport 8080 -j DROP',
                    ckeDesc: 'Reguła odrzucająca pakiety bez powiadomienia nadawcy.',
                    syntaxHint: 'iptables -A INPUT -p tcp --dport 8080 -j DROP',
                    hint: 'Wpisz `iptables -A INPUT -p tcp --dport 8080 -j DROP`.',
                    validate: (cmd) => /iptables.*-a\s+input.*--dport\s+8080.*-j\s+drop/i.test(cmd)
                },
                {
                    task: 'Zezwól na ruch na porcie HTTPS (443): iptables -A INPUT -p tcp --dport 443 -j ACCEPT',
                    ckeDesc: 'Zezwolenie na ruch szyfrowany SSL/TLS.',
                    syntaxHint: 'iptables -A INPUT -p tcp --dport 443 -j ACCEPT',
                    hint: 'Wpisz `iptables -A INPUT -p tcp --dport 443 -j ACCEPT`.',
                    validate: (cmd) => /iptables.*-a\s+input.*--dport\s+443.*-j\s+accept/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj reguły poleceniem: iptables -L',
                    ckeDesc: 'Kontrola stanu zapory po dodaniu reguł.',
                    syntaxHint: 'iptables -L',
                    hint: 'Wpisz `iptables -L`.',
                    validate: (cmd) => /^iptables\s+-L/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_ufw_secure',
            title: 'Zarządzanie zaporą UFW (Uncomplicated Firewall)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★☆☆',
            xp: 25,
            os: 'linux',
            desc: 'Skonfiguruj reguły UFW, zezwalając na usługi SSH i HTTP, a następnie włącz zaporę.',
            steps: [
                {
                    task: 'Sprawdź stan zapory: ufw status',
                    ckeDesc: 'Odczyt statusu aktywności nakładki UFW na netfilter.',
                    syntaxHint: 'ufw status',
                    hint: 'Wpisz `ufw status`.',
                    validate: (cmd) => /^ufw\s+status/i.test(cmd)
                },
                {
                    task: 'Zezwól na ruch SSH: ufw allow 22/tcp',
                    ckeDesc: 'Otwarcie portu zarządzania zdalnego.',
                    syntaxHint: 'ufw allow 22/tcp',
                    hint: 'Wpisz `ufw allow 22/tcp`.',
                    validate: (cmd) => /^ufw\s+allow\s+22/i.test(cmd)
                },
                {
                    task: 'Zezwól na ruch HTTP: ufw allow 80/tcp',
                    ckeDesc: 'Otwarcie portu serwera WWW.',
                    syntaxHint: 'ufw allow 80/tcp',
                    hint: 'Wpisz `ufw allow 80/tcp`.',
                    validate: (cmd) => /^ufw\s+allow\s+80/i.test(cmd)
                },
                {
                    task: 'Aktywuj zaporę: ufw enable',
                    ckeDesc: 'Włączenie reguł i autostartu zapory.',
                    syntaxHint: 'ufw enable',
                    hint: 'Wpisz `ufw enable`.',
                    validate: (cmd) => /^ufw\s+enable/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_diskpart_vol',
            title: 'Zarządzanie woluminami w Windows DiskPart',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Uruchom narzędzie diskpart, wybierz dysk, utwórz partycję podstawową, sformatuj w NTFS i przypisz literę.',
            steps: [
                {
                    task: 'Uruchom narzędzie konsolowe DiskPart: diskpart',
                    ckeDesc: 'Uruchomienie podpowłoki zarządzania dyskami i partycjami.',
                    syntaxHint: 'diskpart',
                    hint: 'Wpisz `diskpart`.',
                    validate: (cmd) => /^diskpart/i.test(cmd)
                },
                {
                    task: 'Wyświetl listę dysków: list disk',
                    ckeDesc: 'Identyfikacja numeru dysku twardego.',
                    syntaxHint: 'list disk',
                    hint: 'Wpisz `list disk`.',
                    validate: (cmd) => /^list\s+disk/i.test(cmd)
                },
                {
                    task: 'Wybierz dysk 1: select disk 1',
                    ckeDesc: 'Ustawienie fokusu na dysk operacyjny.',
                    syntaxHint: 'select disk 1',
                    hint: 'Wpisz `select disk 1`.',
                    validate: (cmd) => /^select\s+disk\s+1/i.test(cmd)
                },
                {
                    task: 'Utwórz partycję podstawową: create partition primary',
                    ckeDesc: 'Alokacja nowej partycji w tabeli MBR/GPT.',
                    syntaxHint: 'create partition primary',
                    hint: 'Wpisz `create partition primary`.',
                    validate: (cmd) => /^create\s+partition\s+primary/i.test(cmd)
                },
                {
                    task: 'Sformatuj wolumin: format fs=ntfs quick label="Dane"',
                    ckeDesc: 'Szybkie formatowanie w systemie plików NTFS z etykietą.',
                    syntaxHint: 'format fs=ntfs quick label="Dane"',
                    hint: 'Wpisz `format fs=ntfs quick label="Dane"`.',
                    validate: (cmd) => /^format\s+fs=ntfs/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf03_mysql_db',
            title: 'Zarządzanie relacyjną bazą danych MySQL',
            cat: 'inf03_db',
            catLabel: 'INF.03 Bazy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 35,
            os: 'linux',
            desc: 'Połącz się z klientem MySQL, stwórz nową bazę danych, tabelę i wykonaj zapytanie SELECT.',
            steps: [
                {
                    task: 'Uruchom klienta MySQL wpisując: mysql',
                    ckeDesc: 'Połączenie z serwerem bazodanowym MySQL/MariaDB.',
                    syntaxHint: 'mysql',
                    hint: 'Wpisz `mysql`.',
                    validate: (cmd) => /^mysql/i.test(cmd)
                },
                {
                    task: 'Utwórz bazę danych: CREATE DATABASE szkola;',
                    ckeDesc: 'Tworzenie nowego schematu bazy danych.',
                    syntaxHint: 'CREATE DATABASE szkola;',
                    hint: 'Wpisz `CREATE DATABASE szkola;`.',
                    validate: (cmd) => /^create\s+database\s+szkola/i.test(cmd)
                },
                {
                    task: 'Wybierz bazę: USE szkola;',
                    ckeDesc: 'Przełączenie kontekstu na bazę szkola.',
                    syntaxHint: 'USE szkola;',
                    hint: 'Wpisz `USE szkola;`.',
                    validate: (cmd) => /^use\s+szkola/i.test(cmd)
                },
                {
                    task: 'Utwórz tabelę: CREATE TABLE uczniowie (id INT, imie VARCHAR(50));',
                    ckeDesc: 'Definicja struktury tabeli (kolumny i typy danych).',
                    syntaxHint: 'CREATE TABLE uczniowie (id INT, imie VARCHAR(50));',
                    hint: 'Wpisz `CREATE TABLE uczniowie (id INT, imie VARCHAR(50));`.',
                    validate: (cmd) => /^create\s+table\s+uczniowie/i.test(cmd)
                },
                {
                    task: 'Wyświetl tabele w bazie: SHOW TABLES;',
                    ckeDesc: 'Weryfikacja utworzonych tabel.',
                    syntaxHint: 'SHOW TABLES;',
                    hint: 'Wpisz `SHOW TABLES;`.',
                    validate: (cmd) => /^show\s+tables/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_chmod_file',
            title: 'Zarządzanie uprawnieniami (chmod 750) i właścicielem',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'primary',
            stars: '★☆☆',
            xp: 25,
            os: 'linux',
            desc: 'Zmień prawa dostępu do pliku script.sh na 750 (rwxr-x---) oraz zmień grupę na admin.',
            steps: [
                {
                    task: 'Sprawdź bieżące uprawnienia: ls -la script.sh',
                    ckeDesc: 'Odczyt bitów praw rwx właściciela, grupy i innych.',
                    syntaxHint: 'ls -la script.sh',
                    hint: 'Wpisz `ls -la script.sh`.',
                    validate: (cmd) => /^ls\s+.*script\.sh/i.test(cmd)
                },
                {
                    task: 'Ustaw prawa dostępu 750: chmod 750 script.sh',
                    ckeDesc: 'Prawa: u=rwx (7), g=r-x (5), o=--- (0).',
                    syntaxHint: 'chmod 750 script.sh',
                    hint: 'Wpisz `chmod 750 script.sh`.',
                    validate: (cmd, os, vfs) => { const n = vfs.getNode('/home/student/script.sh', false); return (n && n.permissions === '750') || /chmod\s+750/i.test(cmd); }
                },
                {
                    task: 'Zmień grupę na admin: chgrp admin script.sh',
                    ckeDesc: 'Zmiana grupy właścicielskiej pliku.',
                    syntaxHint: 'chgrp admin script.sh',
                    hint: 'Wpisz `chgrp admin script.sh`.',
                    validate: (cmd, os, vfs) => { const n = vfs.getNode('/home/student/script.sh', false); return (n && n.group === 'admin') || /chgrp\s+admin/i.test(cmd); }
                },
                {
                    task: 'Zweryfikuj zmiany: ls -la script.sh',
                    ckeDesc: 'Kontrola atrybutów pliku po zmianie.',
                    syntaxHint: 'ls -la script.sh',
                    hint: 'Wpisz `ls -la script.sh`.',
                    validate: (cmd) => /^ls\s+.*script\.sh/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_powershell_diag',
            title: 'Diagnostyka sieci i usług w PowerShell',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Wykorzystaj cmdlets PowerShell do sprawdzenia adresacji IP, stanu usług i testowania połączeń TCP.',
            steps: [
                {
                    task: 'Uruchom konsolę PowerShell wpisując polecenie: powershell',
                    ckeDesc: 'Wejście do środowiska PowerShell 7.',
                    syntaxHint: 'powershell',
                    hint: 'Wpisz `powershell`.',
                    validate: (cmd) => /^powershell/i.test(cmd)
                },
                {
                    task: 'Wyświetl konfigurację adresów IP: Get-NetIPAddress',
                    ckeDesc: 'Cmdlet pobierający informacje o adresach IP interfejsów.',
                    syntaxHint: 'Get-NetIPAddress',
                    hint: 'Wpisz `Get-NetIPAddress`.',
                    validate: (cmd) => /^Get-NetIPAddress/i.test(cmd)
                },
                {
                    task: 'Sprawdź stan usługi serwera W3SVC: Get-Service W3SVC',
                    ckeDesc: 'Cmdlet kontroli stanu usług Windows.',
                    syntaxHint: 'Get-Service W3SVC',
                    hint: 'Wpisz `Get-Service W3SVC`.',
                    validate: (cmd) => /^Get-Service\s+.*w3svc/i.test(cmd)
                },
                {
                    task: 'Przetestuj połączenie z portem 80: Test-NetConnection -ComputerName localhost -Port 80',
                    ckeDesc: 'Test handshake TCP (syn/ack) na określonym porcie.',
                    syntaxHint: 'Test-NetConnection -ComputerName localhost -Port 80',
                    hint: 'Wpisz `Test-NetConnection -ComputerName localhost -Port 80`.',
                    validate: (cmd) => /^Test-NetConnection/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_user_mgmt',
            title: 'Zarządzanie użytkownikami i grupami w Windows (net user)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Utwórz konto użytkownika "anna", stwórz grupę lokalną "Zarzad" i przypisz użytkownika do grupy.',
            steps: [
                {
                    task: 'Utwórz konto użytkownika: net user anna ZaQ!2wsx /add',
                    ckeDesc: 'Tworzenie konta lokalnego z hasłem spełniającym wymogi złożoności.',
                    syntaxHint: 'net user anna ZaQ!2wsx /add',
                    hint: 'Wpisz `net user anna ZaQ!2wsx /add`.',
                    validate: (cmd) => /net\s+user\s+anna.*\/add/i.test(cmd)
                },
                {
                    task: 'Utwórz grupę lokalną: net localgroup "Zarzad" /add',
                    ckeDesc: 'Tworzenie nowej grupy lokalnej w systemie Windows.',
                    syntaxHint: 'net localgroup "Zarzad" /add',
                    hint: 'Wpisz `net localgroup "Zarzad" /add`.',
                    validate: (cmd) => /net\s+localgroup.*Zarzad.*\/add/i.test(cmd)
                },
                {
                    task: 'Dodaj użytkownika do grupy: net localgroup "Zarzad" anna /add',
                    ckeDesc: 'Członkostwo użytkownika w grupie uprawnień.',
                    syntaxHint: 'net localgroup "Zarzad" anna /add',
                    hint: 'Wpisz `net localgroup "Zarzad" anna /add`.',
                    validate: (cmd) => /net\s+localgroup.*Zarzad.*anna.*\/add/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj konto użytkownika: net user anna',
                    ckeDesc: 'Odczyt właściwości konta i przynależności do grup.',
                    syntaxHint: 'net user anna',
                    hint: 'Wpisz `net user anna`.',
                    validate: (cmd) => /net\s+user\s+anna/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_share_icacls',
            title: 'Udział sieciowy i uprawnienia NTFS (icacls)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★★',
            xp: 35,
            os: 'windows',
            desc: 'Utwórz katalog C:\\Dane, nadaj grupie Users prawa Modyfikacji (OI)(CI)M i udostępnij w sieci jako "Projekty".',
            steps: [
                {
                    task: 'Utwórz folder: md C:\\Dane',
                    ckeDesc: 'Tworzenie katalogu na dysku C:.',
                    syntaxHint: 'md C:\\Dane',
                    hint: 'Wpisz `md C:\\Dane`.',
                    validate: (cmd) => /(md|mkdir)\s+.*Dane/i.test(cmd)
                },
                {
                    task: 'Nadaj uprawnienia NTFS dla grupy Users: icacls C:\\Dane /grant Users:(OI)(CI)M',
                    ckeDesc: 'Konfiguracja dziedziczenia Object Inherit (OI) i Container Inherit (CI) z prawem Modyfikacji (M).',
                    syntaxHint: 'icacls C:\\Dane /grant Users:(OI)(CI)M',
                    hint: 'Wpisz `icacls C:\\Dane /grant Users:(OI)(CI)M`.',
                    validate: (cmd) => /icacls.*Dane.*\/grant.*Users/i.test(cmd)
                },
                {
                    task: 'Udostępnij folder w sieci: net share Projekty=C:\\Dane /grant:everyone,full',
                    ckeDesc: 'Tworzenie udziału SMB z uprawnieniami udostępniania.',
                    syntaxHint: 'net share Projekty=C:\\Dane /grant:everyone,full',
                    hint: 'Wpisz `net share Projekty=C:\\Dane /grant:everyone,full`.',
                    validate: (cmd) => /net\s+share\s+Projekty/i.test(cmd)
                },
                {
                    task: 'Sprawdź listę aktywnych udziałów: net share',
                    ckeDesc: 'Weryfikacja czy udział Projekty znajduje się na liście.',
                    syntaxHint: 'net share',
                    hint: 'Wpisz `net share`.',
                    validate: (cmd) => /^net\s+share/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf03_mysql_grant',
            title: 'Tworzenie użytkownika i uprawnień GRANT w MySQL',
            cat: 'inf03_db',
            catLabel: 'INF.03 Bazy',
            badgeColor: 'info',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Utwórz użytkownika bazy danych "aplikacja" i nadaj mu pełne uprawnienia do bazy "szkola".',
            steps: [
                {
                    task: 'Uruchom konsolę MySQL: mysql',
                    ckeDesc: 'Połączenie z serwerem bazodanowym.',
                    syntaxHint: 'mysql',
                    hint: 'Wpisz `mysql`.',
                    validate: (cmd) => /^mysql/i.test(cmd)
                },
                {
                    task: 'Utwórz konto: CREATE USER \'aplikacja\'@\'localhost\' IDENTIFIED BY \'Haslo123!\';',
                    ckeDesc: 'Tworzenie konta użytkownika z ograniczeniem do localhost.',
                    syntaxHint: 'CREATE USER \'aplikacja\'@\'localhost\' IDENTIFIED BY \'Haslo123!\';',
                    hint: 'Wpisz `CREATE USER \'aplikacja\'@\'localhost\' IDENTIFIED BY \'Haslo123!\';`.',
                    validate: (cmd) => /create\s+user.*aplikacja/i.test(cmd)
                },
                {
                    task: 'Nadaj uprawnienia: GRANT ALL PRIVILEGES ON szkola.* TO \'aplikacja\'@\'localhost\';',
                    ckeDesc: 'Przyznanie praw DML/DDL do tabel w schemacie szkola.',
                    syntaxHint: 'GRANT ALL PRIVILEGES ON szkola.* TO \'aplikacja\'@\'localhost\';',
                    hint: 'Wpisz `GRANT ALL PRIVILEGES ON szkola.* TO \'aplikacja\'@\'localhost\';`.',
                    validate: (cmd) => /grant.*all.*on\s+szkola/i.test(cmd)
                },
                {
                    task: 'Zastosuj zmiany: FLUSH PRIVILEGES;',
                    ckeDesc: 'Przeładowanie tabel uprawnień w pamięci podręcznej MySQL.',
                    syntaxHint: 'FLUSH PRIVILEGES;',
                    hint: 'Wpisz `FLUSH PRIVILEGES;`.',
                    validate: (cmd) => /^flush\s+privileges/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_nmap_audit',
            title: 'Audyt otwartych portów i usług (Nmap)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Przeprowadź skanowanie TCP SYN oraz detekcję wersji usług działających na serwerze.',
            steps: [
                {
                    task: 'Wykonaj szybkie skanowanie portów lokalnych: nmap 127.0.0.1',
                    ckeDesc: 'Skanowanie 1000 najpopularniejszych portów TCP na pętli zwrotnej.',
                    syntaxHint: 'nmap 127.0.0.1',
                    hint: 'Wpisz `nmap 127.0.0.1`.',
                    validate: (cmd) => /^nmap\s+.*127\.0\.0\.1/i.test(cmd)
                },
                {
                    task: 'Wykonaj skanowanie TCP SYN zakresu portów: nmap -sS -p 1-1000 192.168.1.1',
                    ckeDesc: 'Dyskretne skanowanie SYN stealth scan (half-open).',
                    syntaxHint: 'nmap -sS -p 1-1000 192.168.1.1',
                    hint: 'Wpisz `nmap -sS -p 1-1000 192.168.1.1`.',
                    validate: (cmd) => /^nmap.*-sS/i.test(cmd)
                },
                {
                    task: 'Wykryj wersje usług na portach 22, 80, 443: nmap -sV -p 22,80,443 192.168.1.100',
                    ckeDesc: 'Weryfikacja banerów wersji oprogramowania serwerowego.',
                    syntaxHint: 'nmap -sV -p 22,80,443 192.168.1.100',
                    hint: 'Wpisz `nmap -sV -p 22,80,443 192.168.1.100`.',
                    validate: (cmd) => /^nmap.*-sV/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_ssl_cert',
            title: 'Generowanie certyfikatu SSL/TLS (OpenSSL)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Wygeneruj 2048-bitowy klucz prywatny RSA oraz samopodpisany certyfikat X.509 na 365 dni.',
            steps: [
                {
                    task: 'Wygeneruj klucz i certyfikat: openssl req -x509 -newkey rsa:2048 -keyout /etc/ssl/private/server.key -out /etc/ssl/certs/server.crt -days 365 -nodes',
                    ckeDesc: 'Tworzenie pary kluczy kryptograficznych i certyfikatu dla protokołu HTTPS.',
                    syntaxHint: 'openssl req -x509 -newkey rsa:2048 -keyout /etc/ssl/private/server.key -out /etc/ssl/certs/server.crt -days 365 -nodes',
                    hint: 'Wpisz całe polecenie `openssl req -x509 ...`.',
                    validate: (cmd) => /openssl\s+req.*-x509/i.test(cmd)
                },
                {
                    task: 'Sprawdź utworzony plik certyfikatu: ls -la /etc/ssl/certs/server.crt',
                    ckeDesc: 'Weryfikacja obecności pliku certyfikatu.',
                    syntaxHint: 'ls -la /etc/ssl/certs/server.crt',
                    hint: 'Wpisz `ls -la /etc/ssl/certs/server.crt`.',
                    validate: (cmd) => /^ls\s+.*server\.crt/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_crontab_backup',
            title: 'Automatyzacja kopii zapasowych w cronie',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Sprawdź harmonogram zadań cron, utwórz archiwum tar.gz katalogu /home/student i wyświetl zawartość.',
            steps: [
                {
                    task: 'Wyświetl bieżącą tabelę crona: crontab -l',
                    ckeDesc: 'Odczyt zadań okresowych zalogowanego użytkownika.',
                    syntaxHint: 'crontab -l',
                    hint: 'Wpisz `crontab -l`.',
                    validate: (cmd) => /^crontab\s+-l/i.test(cmd)
                },
                {
                    task: 'Utwórz skompresowane archiwum tar: tar -czf backup.tar.gz /home/student',
                    ckeDesc: 'Kompensacja katalogu domowego do archiwum .tar.gz.',
                    syntaxHint: 'tar -czf backup.tar.gz /home/student',
                    hint: 'Wpisz `tar -czf backup.tar.gz /home/student`.',
                    validate: (cmd) => /^tar\s+.*-?c[zfv]*f?\s+backup\.tar\.gz/i.test(cmd)
                },
                {
                    task: 'Wyświetl zawartość utworzonego archiwum: tar -tf backup.tar.gz',
                    ckeDesc: 'Listowanie plików wewnątrz archiwum bez dekompresji.',
                    syntaxHint: 'tar -tf backup.tar.gz',
                    hint: 'Wpisz `tar -tf backup.tar.gz`.',
                    validate: (cmd) => /^tar\s+.*-?t[zfv]*f?\s+backup\.tar\.gz/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_ssh_hardening',
            title: 'Zabezpieczenie serwera SSH (sshd_config)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Zmodyfikuj konfigurację serwera OpenSSH (/etc/ssh/sshd_config) i zrestartuj usługę.',
            steps: [
                {
                    task: 'Otwórz konfigurację demona SSH: nano /etc/ssh/sshd_config',
                    ckeDesc: 'Edycja dyrektyw PermitRootLogin oraz PasswordAuthentication.',
                    syntaxHint: 'nano /etc/ssh/sshd_config',
                    hint: 'Wpisz `nano /etc/ssh/sshd_config`.',
                    validate: (cmd) => /^nano\s+.*sshd_config/i.test(cmd)
                },
                {
                    task: 'Zrestartuj usługę SSH aby załadować nowe ustawienia: systemctl restart ssh',
                    ckeDesc: 'Zastosowanie nowej polityki bezpieczeństwa SSH.',
                    syntaxHint: 'systemctl restart ssh',
                    hint: 'Wpisz `systemctl restart ssh`.',
                    validate: (cmd) => /^systemctl\s+(restart|start|reload)\s+ssh(d)?/i.test(cmd)
                },
                {
                    task: 'Sprawdź stan i port nasłuchiwania SSH: ss -tuln',
                    ckeDesc: 'Kontrola nasłuchiwania gniazda TCP na porcie 22.',
                    syntaxHint: 'ss -tuln',
                    hint: 'Wpisz `ss -tuln` lub `netstat -tuln`.',
                    validate: (cmd) => /^(ss|netstat)\s+-(tuln|tulpn|an)/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_sfc_dism',
            title: 'Diagnostyka i naprawa integralności Windows (SFC / DISM)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Przeprowadź weryfikację chronionych plików systemowych (sfc /scannow) oraz napraw obraz przez DISM.',
            steps: [
                {
                    task: 'Uruchom narzędzie System File Checker: sfc /scannow',
                    ckeDesc: 'Weryfikacja podpisów cyfrowych plików DLL i EXE.',
                    syntaxHint: 'sfc /scannow',
                    hint: 'Wpisz `sfc /scannow`.',
                    validate: (cmd) => /^sfc\s+\/scannow/i.test(cmd)
                },
                {
                    task: 'Napraw magazyn składników Windows: dism /online /cleanup-image /restorehealth',
                    ckeDesc: 'Pobieranie i naprawa uszkodzonych pakietów manifestu Windows.',
                    syntaxHint: 'dism /online /cleanup-image /restorehealth',
                    hint: 'Wpisz `dism /online /cleanup-image /restorehealth`.',
                    validate: (cmd) => /^dism.*\/restorehealth/i.test(cmd)
                },
                {
                    task: 'Sprawdź spójność systemu plików dysku: chkdsk',
                    ckeDesc: 'Skanowanie struktury MFT i woluminu NTFS.',
                    syntaxHint: 'chkdsk',
                    hint: 'Wpisz `chkdsk`.',
                    validate: (cmd) => /^chkdsk/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_grep_sed_logs',
            title: 'Filtrowanie i analiza logów systemowych (grep, cut, sort)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 25,
            os: 'linux',
            desc: 'Wyszukaj wpisy w /var/log/syslog, wyodrębnij nazwy użytkowników z /etc/passwd i posortuj alfabetycznie.',
            steps: [
                {
                    task: 'Wyszukaj wpisy o procesie systemd w syslogu: grep systemd /var/log/syslog',
                    ckeDesc: 'Filtrowanie strumienia tekstowego za pomocą wyrażeń regularnych.',
                    syntaxHint: 'grep systemd /var/log/syslog',
                    hint: 'Wpisz `grep systemd /var/log/syslog`.',
                    validate: (cmd) => /^grep.*systemd/i.test(cmd)
                },
                {
                    task: 'Wyświetl pierwszych 5 wierszy pliku /etc/passwd: head -n 5 /etc/passwd',
                    ckeDesc: 'Odczyt nagłówka pliku konfiguracyjnego kont.',
                    syntaxHint: 'head -n 5 /etc/passwd',
                    hint: 'Wpisz `head -n 5 /etc/passwd`.',
                    validate: (cmd) => /^head\s+-n\s+5/i.test(cmd)
                },
                {
                    task: 'Wytnij pierwszą kolumnę (login) z /etc/passwd: cut -d: -f1 /etc/passwd',
                    ckeDesc: 'Ekstrakcja pól rozdzielanych dwukropkiem.',
                    syntaxHint: 'cut -d: -f1 /etc/passwd',
                    hint: 'Wpisz `cut -d: -f1 /etc/passwd`.',
                    validate: (cmd) => /^cut\s+-d:?\s+-f1/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_lvm_volumes',
            title: 'Konfiguracja woluminów logicznych LVM',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★★',
            xp: 40,
            os: 'linux',
            desc: 'Utwórz wolumin fizyczny, grupę woluminów, wolumin logiczny lv_dane, sformatuj w ext4 i zamontuj w /mnt/dane.',
            steps: [
                {
                    task: 'Zainicjuj wolumin fizyczny PV na dysku /dev/sdb1: pvcreate /dev/sdb1',
                    ckeDesc: 'Tworzenie struktury LVM Physical Volume na partycji dysku.',
                    syntaxHint: 'pvcreate /dev/sdb1',
                    hint: 'Wpisz `pvcreate /dev/sdb1`.',
                    validate: (cmd) => /^pvcreate\s+\/dev\/sdb1/i.test(cmd)
                },
                {
                    task: 'Utwórz grupę woluminów VG o nazwie vg_dane: vgcreate vg_dane /dev/sdb1',
                    ckeDesc: 'Łączenie woluminów fizycznych w pulę Volume Group.',
                    syntaxHint: 'vgcreate vg_dane /dev/sdb1',
                    hint: 'Wpisz `vgcreate vg_dane /dev/sdb1`.',
                    validate: (cmd) => /^vgcreate\s+vg_dane\s+\/dev\/sdb1/i.test(cmd)
                },
                {
                    task: 'Utwórz wolumin logiczny LV o rozmiarze 10G i nazwie lv_dane: lvcreate -n lv_dane -L 10G vg_dane',
                    ckeDesc: 'Wydzielenie woluminu logicznego z grupy woluminów.',
                    syntaxHint: 'lvcreate -n lv_dane -L 10G vg_dane',
                    hint: 'Wpisz `lvcreate -n lv_dane -L 10G vg_dane` lub `lvcreate -L 10G -n lv_dane vg_dane`.',
                    validate: (cmd) => /^lvcreate.*-n\s+lv_dane.*vg_dane/i.test(cmd) || /^lvcreate.*-L\s+10G.*vg_dane/i.test(cmd)
                },
                {
                    task: 'Sformatuj wolumin logiczny w systemie plików ext4: mkfs.ext4 /dev/vg_dane/lv_dane',
                    ckeDesc: 'Tworzenie systemu plików na nowo utworzonym woluminie logicznym.',
                    syntaxHint: 'mkfs.ext4 /dev/vg_dane/lv_dane',
                    hint: 'Wpisz `mkfs.ext4 /dev/vg_dane/lv_dane`.',
                    validate: (cmd) => /^mkfs\.ext4\s+\/dev\/vg_dane\/lv_dane/i.test(cmd)
                },
                {
                    task: 'Zamontuj wolumin logiczny w punkcie montowania /mnt/dane: mount /dev/vg_dane/lv_dane /mnt/dane',
                    ckeDesc: 'Montowanie systemu plików w drzewie katalogów Linux.',
                    syntaxHint: 'mount /dev/vg_dane/lv_dane /mnt/dane',
                    hint: 'Wpisz `mount /dev/vg_dane/lv_dane /mnt/dane`.',
                    validate: (cmd) => /^mount\s+\/dev\/vg_dane\/lv_dane\s+\/mnt\/dane/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_raid1_mdadm',
            title: 'Tworzenie macierzy dyskowej RAID 1 w mdadm',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★★',
            xp: 40,
            os: 'linux',
            desc: 'Utwórz programową macierz lustrzaną RAID 1 (/dev/md0) z dwóch dysków /dev/sdb i /dev/sdc.',
            steps: [
                {
                    task: 'Utwórz macierz RAID 1: mdadm --create /dev/md0 --level=1 --raid-devices=2 /dev/sdb /dev/sdc',
                    ckeDesc: 'Konfiguracja lustrzanej macierzy dyskowej zapewniającej redundancję danych.',
                    syntaxHint: 'mdadm --create /dev/md0 --level=1 --raid-devices=2 /dev/sdb /dev/sdc',
                    hint: 'Wpisz `mdadm --create /dev/md0 --level=1 --raid-devices=2 /dev/sdb /dev/sdc`.',
                    validate: (cmd) => /^mdadm\s+(--create|-C)\s+\/dev\/md0.*(--level=1|-l\s*1).*\/dev\/sdb/i.test(cmd)
                },
                {
                    task: 'Sprawdź stan synchronizacji macierzy w pliku /proc/mdstat: cat /proc/mdstat',
                    ckeDesc: 'Odczyt wirtualnego pliku statusu sterownika programowego RAID w jądrze Linux.',
                    syntaxHint: 'cat /proc/mdstat',
                    hint: 'Wpisz `cat /proc/mdstat`.',
                    validate: (cmd) => /^cat\s+\/proc\/mdstat/i.test(cmd)
                },
                {
                    task: 'Wyświetl szczegółowe informacje o macierzy: mdadm --detail /dev/md0',
                    ckeDesc: 'Weryfikacja stanu urządzeń składowych i sum kontrolnych macierzy.',
                    syntaxHint: 'mdadm --detail /dev/md0',
                    hint: 'Wpisz `mdadm --detail /dev/md0`.',
                    validate: (cmd) => /^mdadm\s+(--detail|-D)\s+\/dev\/md0/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_ps_dhcp_dns',
            title: 'PowerShell: Zarządzanie rolami DHCP i DNS w Windows',
            cat: 'inf02_net',
            catLabel: 'INF.02 Windows',
            badgeColor: 'primary',
            stars: '★★★',
            xp: 35,
            os: 'windows',
            desc: 'Utwórz zakres adresów DHCP oraz strefę wyszukiwania i rekord A w serwerze DNS za pomocą poleceń PowerShell.',
            steps: [
                {
                    task: 'Utwórz zakres DHCP: Add-DhcpServerv4Scope -Name "Podsiec_Pracownia" -StartRange 192.168.10.100 -EndRange 192.168.10.200 -SubnetMask 255.255.255.0',
                    ckeDesc: 'Konfiguracja puli przydziału adresów IPv4 dla stacji roboczych.',
                    syntaxHint: 'Add-DhcpServerv4Scope -Name "Podsiec_Pracownia" -StartRange 192.168.10.100 -EndRange 192.168.10.200 -SubnetMask 255.255.255.0',
                    hint: 'Wpisz `Add-DhcpServerv4Scope -Name "Podsiec_Pracownia" -StartRange 192.168.10.100 -EndRange 192.168.10.200 -SubnetMask 255.255.255.0`.',
                    validate: (cmd) => /Add-DhcpServerv4Scope.*192\.168\.10\.100/i.test(cmd)
                },
                {
                    task: 'Dodaj strefę podstawową DNS: Add-DnsServerPrimaryZone -Name "zsem.local" -ZoneFile "zsem.local.dns"',
                    ckeDesc: 'Tworzenie strefy wyszukiwania do przodu w usłudze Microsoft DNS.',
                    syntaxHint: 'Add-DnsServerPrimaryZone -Name "zsem.local" -ZoneFile "zsem.local.dns"',
                    hint: 'Wpisz `Add-DnsServerPrimaryZone -Name "zsem.local" -ZoneFile "zsem.local.dns"`.',
                    validate: (cmd) => /Add-DnsServerPrimaryZone.*zsem\.local/i.test(cmd)
                },
                {
                    task: 'Utwórz rekord hosta (A): Add-DnsServerResourceRecordA -ZoneName "zsem.local" -Name "serwer" -IPv4Address "192.168.10.10"',
                    ckeDesc: 'Mapowanie nazwy domenowej serwera na statyczny adres IPv4.',
                    syntaxHint: 'Add-DnsServerResourceRecordA -ZoneName "zsem.local" -Name "serwer" -IPv4Address "192.168.10.10"',
                    hint: 'Wpisz `Add-DnsServerResourceRecordA -ZoneName "zsem.local" -Name "serwer" -IPv4Address "192.168.10.10"`.',
                    validate: (cmd) => /Add-DnsServerResourceRecordA.*zsem\.local.*192\.168\.10\.10/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf03_mysql_adv_grant',
            title: 'MySQL: Relacje tabel, klucze obce i uprawnienia GRANT',
            cat: 'inf03_db',
            catLabel: 'INF.03 Bazy',
            badgeColor: 'success',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Utwórz użytkownika bazy danych technik i nadaj uprawnienia SELECT oraz INSERT do bazy egzamin.',
            steps: [
                {
                    task: 'Uruchom monitor bazy danych: mysql -u root -p',
                    ckeDesc: 'Logowanie do konsoli CLI serwera bazy danych MySQL / MariaDB.',
                    syntaxHint: 'mysql -u root -p',
                    hint: 'Wpisz `mysql -u root -p` lub po prostu `mysql`.',
                    validate: (cmd, os, vfs, net, term) => term.currentSubShell === 'mysql' || /^mysql/i.test(cmd)
                },
                {
                    task: 'Utwórz konto użytkownika technik: CREATE USER \'technik\'@\'localhost\' IDENTIFIED BY \'Zsem2026!\';',
                    ckeDesc: 'Definiowanie nowego konta użytkownika bazy danych z uwierzytelnianiem hasłem.',
                    syntaxHint: 'CREATE USER \'technik\'@\'localhost\' IDENTIFIED BY \'Zsem2026!\';',
                    hint: 'Wpisz `CREATE USER \'technik\'@\'localhost\' IDENTIFIED BY \'Zsem2026!\';`.',
                    validate: (cmd) => /CREATE\s+USER.*technik/i.test(cmd)
                },
                {
                    task: 'Nadaj uprawnienia do bazy: GRANT SELECT, INSERT ON egzamin.* TO \'technik\'@\'localhost\';',
                    ckeDesc: 'Przyznawanie selektywnych praw DML do bazy danych zgodnie z zasadą najmniejszych uprawnień.',
                    syntaxHint: 'GRANT SELECT, INSERT ON egzamin.* TO \'technik\'@\'localhost\';',
                    hint: 'Wpisz `GRANT SELECT, INSERT ON egzamin.* TO \'technik\'@\'localhost\';`.',
                    validate: (cmd) => /GRANT\s+SELECT.*INSERT.*ON.*TO.*technik/i.test(cmd)
                },
                {
                    task: 'Przeładuj tabele uprawnień serwera: FLUSH PRIVILEGES;',
                    ckeDesc: 'Wymuszenie natychmiastowego załadowania zmienionych uprawnień przez silnik MySQL.',
                    syntaxHint: 'FLUSH PRIVILEGES;',
                    hint: 'Wpisz `FLUSH PRIVILEGES;`.',
                    validate: (cmd) => /FLUSH\s+PRIVILEGES/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf03_mysqldump_backup',
            title: 'Wykonywanie i przywracanie kopii bazy (mysqldump)',
            cat: 'inf03_db',
            catLabel: 'INF.03 Bazy',
            badgeColor: 'success',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Wykonaj pełny zrzut bazy danych bazatest do pliku backup.sql i sprawdź jego rozmiar.',
            steps: [
                {
                    task: 'Wykonaj kopię bazy danych: mysqldump -u root -p bazatest > backup.sql',
                    ckeDesc: 'Eksport struktury tabel i rekordów do formatu instrukcji SQL.',
                    syntaxHint: 'mysqldump -u root -p bazatest > backup.sql',
                    hint: 'Wpisz `mysqldump -u root -p bazatest > backup.sql`.',
                    validate: (cmd) => /mysqldump.*bazatest.*backup\.sql/i.test(cmd) || /mysqldump/i.test(cmd)
                },
                {
                    task: 'Sprawdź istnienie i rozmiar pliku zrzutu: ls -lh backup.sql',
                    ckeDesc: 'Weryfikacja poprawności utworzenia pliku kopii zapasowej.',
                    syntaxHint: 'ls -lh backup.sql',
                    hint: 'Wpisz `ls -lh backup.sql` lub `ls -l`.',
                    validate: (cmd) => /^ls.*backup\.sql/i.test(cmd) || /^ls/i.test(cmd)
                },
                {
                    task: 'Zaimportuj kopię do nowej bazy: mysql -u root -p nowa_baza < backup.sql',
                    ckeDesc: 'Odtworzenie bazy danych z pliku tekstowego zrzutu SQL.',
                    syntaxHint: 'mysql -u root -p nowa_baza < backup.sql',
                    hint: 'Wpisz `mysql -u root -p nowa_baza < backup.sql`.',
                    validate: (cmd) => /mysql.*nowa_baza.*backup\.sql/i.test(cmd) || /mysql/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_ssh_hardened_cfg',
            title: 'Zaawansowane utwardzanie serwera OpenSSH',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Bezpieczeństwo',
            badgeColor: 'danger',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Otwórz /etc/ssh/sshd_config w edytorze nano, przetestuj składnię sshd -t i zrestartuj usługę ssh.',
            steps: [
                {
                    task: 'Otwórz konfigurację serwera SSH w nano: nano /etc/ssh/sshd_config',
                    ckeDesc: 'Edycja parametrów bezpieczeństwa demona OpenSSH (port, autoryzacja kluczem, root login).',
                    syntaxHint: 'nano /etc/ssh/sshd_config',
                    hint: 'Wpisz `nano /etc/ssh/sshd_config`.',
                    validate: (cmd) => /nano\s+\/etc\/ssh\/sshd_config/i.test(cmd)
                },
                {
                    task: 'Sprawdź poprawność składniową pliku konfiguracyjnego: sshd -t',
                    ckeDesc: 'Weryfikacja konfiguracji przed restartem usługi chroniąca przed odcięciem dostępu.',
                    syntaxHint: 'sshd -t',
                    hint: 'Wpisz `sshd -t`.',
                    validate: (cmd) => /^sshd\s+-t/i.test(cmd)
                },
                {
                    task: 'Zrestartuj usługę serwera SSH: systemctl restart ssh',
                    ckeDesc: 'Załadowanie nowej konfiguracji przez demona sshd.',
                    syntaxHint: 'systemctl restart ssh',
                    hint: 'Wpisz `systemctl restart ssh` lub `systemctl restart sshd`.',
                    validate: (cmd) => /^systemctl\s+restart\s+ssh/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_iptables_nat_portfwd',
            title: 'iptables: Konfiguracja reguł NAT i Port Forwardingu',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Bezpieczeństwo',
            badgeColor: 'danger',
            stars: '★★★',
            xp: 40,
            os: 'linux',
            desc: 'Skonfiguruj maskaradę (SNAT) na interfejsie wyjściowym oraz przekierowanie portu TCP 8080 na serwer wewnętrzny.',
            steps: [
                {
                    task: 'Włącz maskaradę pakietów wychodzących: iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE',
                    ckeDesc: 'Konfiguracja Source NAT (SNAT) umożliwiająca dostęp do internetu dla hostów sieci LAN.',
                    syntaxHint: 'iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE',
                    hint: 'Wpisz `iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE`.',
                    validate: (cmd) => /iptables.*-t\s+nat.*POSTROUTING.*MASQUERADE/i.test(cmd)
                },
                {
                    task: 'Przekieruj port 8080 na wewnętrzny serwer WWW: iptables -t nat -A PREROUTING -p tcp --dport 8080 -j DNAT --to-destination 192.168.1.100:80',
                    ckeDesc: 'Konfiguracja Destination NAT (DNAT / Port Forwarding) dla usług wewnątrz sieci.',
                    syntaxHint: 'iptables -t nat -A PREROUTING -p tcp --dport 8080 -j DNAT --to-destination 192.168.1.100:80',
                    hint: 'Wpisz `iptables -t nat -A PREROUTING -p tcp --dport 8080 -j DNAT --to-destination 192.168.1.100:80`.',
                    validate: (cmd) => /iptables.*-t\s+nat.*PREROUTING.*DNAT.*192\.168\.1\.100/i.test(cmd)
                },
                {
                    task: 'Wyświetl tablicę reguł NAT z licznikami pakietów: iptables -t nat -L -v -n',
                    ckeDesc: 'Weryfikacja stanu reguł tablicy NAT bez rozwiązywania nazw DNS.',
                    syntaxHint: 'iptables -t nat -L -v -n',
                    hint: 'Wpisz `iptables -t nat -L -v -n` lub `iptables -t nat -L`.',
                    validate: (cmd) => /iptables.*-t\s+nat.*-L/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_fail2ban_setup',
            title: 'Ochrona przed atakami brute-force w Fail2ban',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Bezpieczeństwo',
            badgeColor: 'danger',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Sprawdź stan ochrony Fail2ban dla usługi SSH i zablokuj atakujący adres IP.',
            steps: [
                {
                    task: 'Sprawdź stan usługi ochrony: systemctl status fail2ban',
                    ckeDesc: 'Weryfikacja aktywności demona analizującego logi uwierzytelniania.',
                    syntaxHint: 'systemctl status fail2ban',
                    hint: 'Wpisz `systemctl status fail2ban`.',
                    validate: (cmd) => /^systemctl\s+status\s+fail2ban/i.test(cmd)
                },
                {
                    task: 'Sprawdź statystyki zablokowanych adresów w celi sshd: fail2ban-client status sshd',
                    ckeDesc: 'Odczyt listy zablokowanych adresów IP oraz liczby nieudanych prób logowania.',
                    syntaxHint: 'fail2ban-client status sshd',
                    hint: 'Wpisz `fail2ban-client status sshd`.',
                    validate: (cmd) => /^fail2ban-client\s+status\s+sshd/i.test(cmd)
                },
                {
                    task: 'Zablokuj ręcznie adres IP atakującego: fail2ban-client set sshd banip 198.51.100.44',
                    ckeDesc: 'Natychmiastowe zablokowanie adresu w regułach zapory sieciowej przez Fail2ban.',
                    syntaxHint: 'fail2ban-client set sshd banip 198.51.100.44',
                    hint: 'Wpisz `fail2ban-client set sshd banip 198.51.100.44`.',
                    validate: (cmd) => /^fail2ban-client\s+set\s+sshd\s+banip/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_ad_dsadd_mgmt',
            title: 'Zarządzanie Active Directory (dsadd / PowerShell)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Windows',
            badgeColor: 'primary',
            stars: '★★★',
            xp: 35,
            os: 'windows',
            desc: 'Utwórz jednostkę organizacyjną (OU) Pracownicy oraz nowego użytkownika z wymuszeniem zmiany hasła.',
            steps: [
                {
                    task: 'Utwórz jednostkę organizacyjną: dsadd ou "ou=Pracownicy,dc=zsem,dc=local"',
                    ckeDesc: 'Tworzenie struktury logicznej kont w domenie Active Directory.',
                    syntaxHint: 'dsadd ou "ou=Pracownicy,dc=zsem,dc=local"',
                    hint: 'Wpisz `dsadd ou "ou=Pracownicy,dc=zsem,dc=local"`.',
                    validate: (cmd) => /^dsadd\s+ou/i.test(cmd)
                },
                {
                    task: 'Utwórz użytkownika w domenie: dsadd user "cn=Jan Kowalski,ou=Pracownicy,dc=zsem,dc=local" -pwd "ZsemPass123!" -mustchpwd yes',
                    ckeDesc: 'Dodawanie konta domenowego z hasłem startowym i wymogiem zmiany przy pierwszym logowaniu.',
                    syntaxHint: 'dsadd user "cn=Jan Kowalski,ou=Pracownicy,dc=zsem,dc=local" -pwd "ZsemPass123!" -mustchpwd yes',
                    hint: 'Wpisz `dsadd user "cn=Jan Kowalski,ou=Pracownicy,dc=zsem,dc=local" -pwd "ZsemPass123!" -mustchpwd yes`.',
                    validate: (cmd) => /^dsadd\s+user/i.test(cmd)
                },
                {
                    task: 'Utwórz lokalne konto serwisowe w PowerShell: New-LocalUser -Name "Serwis" -Description "Konto serwisowe"',
                    ckeDesc: 'Tworzenie lokalnego konta użytkownika w systemie Windows za pomocą cmdletu PowerShell.',
                    syntaxHint: 'New-LocalUser -Name "Serwis" -Description "Konto serwisowe"',
                    hint: 'Wpisz `New-LocalUser -Name "Serwis" -Description "Konto serwisowe"`.',
                    validate: (cmd) => /New-LocalUser.*Serwis/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_route_diag_traceroute',
            title: 'Diagnostyka routingu sieciowego i trasowania pakietów',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            xp: 30,
            os: 'any',
            desc: 'Wyświetl tablicę routingu, prześledź trasę pakietów do serwera DNS i dodaj trasę statyczną do podsieci 10.50.0.0/24.',
            steps: [
                {
                    task: 'Wyświetl tablicę routingu systemu (Linux: ip route show | Windows: route print)',
                    ckeDesc: 'Odczyt tras statycznych, domyślnej bramy i metryk interfejsów.',
                    syntaxHint: 'ip route show   LUB   route print',
                    hint: 'W Linux wpisz `ip route show` lub `route -n`. W Windows wpisz `route print`.',
                    validate: (cmd, os) => os === 'linux' ? /^(ip\s+route|route)/i.test(cmd) : /^route\s+print/i.test(cmd)
                },
                {
                    task: 'Wykonaj trasowanie pakietów do adresu 8.8.8.8 (traceroute / tracert 8.8.8.8)',
                    ckeDesc: 'Śledzenie kolejnych przeskoków (routerów) na trasie pakietu IP.',
                    syntaxHint: 'traceroute 8.8.8.8   LUB   tracert 8.8.8.8',
                    hint: 'W Linux: `traceroute 8.8.8.8`. W Windows: `tracert 8.8.8.8`.',
                    validate: (cmd) => /^(traceroute|tracert)\s+8\.8\.8\.8/i.test(cmd)
                },
                {
                    task: 'Dodaj trasę statyczną do podsieci 10.50.0.0/24 przez bramę 192.168.1.254 (Linux: ip route add 10.50.0.0/24 via 192.168.1.254 | Win: route add 10.50.0.0 mask 255.255.255.0 192.168.1.254)',
                    ckeDesc: 'Ręczne definiowanie trasy do sieci zdalnej w tablicy trasowania jądra.',
                    syntaxHint: 'ip route add 10.50.0.0/24 via 192.168.1.254   LUB   route add 10.50.0.0 mask 255.255.255.0 192.168.1.254',
                    hint: 'W Linux: `ip route add 10.50.0.0/24 via 192.168.1.254`. W Windows: `route add 10.50.0.0 mask 255.255.255.0 192.168.1.254`.',
                    validate: (cmd) => /(ip\s+route\s+add|route\s+add).*10\.50\.0\.0/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_process_mgmt',
            title: 'Monitorowanie i zakańczanie procesów systemowych',
            cat: 'inf02_admin',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Przeanalizuj listę działających procesów, zlokalizuj proces serwera Apache2 i wyślij sygnał zakończenia działania.',
            steps: [
                {
                    task: 'Wyświetl listę procesów i odfiltruj procesy serwera apache2 (ps aux | grep apache2)',
                    ckeDesc: 'Wyszukiwanie identyfikatora PID i zużycia zasobów konkretnego procesu w systemie Linux.',
                    syntaxHint: 'ps aux | grep apache2',
                    hint: 'Wpisz `ps aux | grep apache2` lub `ps -ef | grep apache2`.',
                    validate: (cmd) => /ps.*grep.*apache/i.test(cmd) || /ps\s+(aux|-ef)/i.test(cmd)
                },
                {
                    task: 'Zakończ proces serwera Apache2 za pomocą polecenia kill, pkill lub killall',
                    ckeDesc: 'Zatrzymanie procesu poprzez wysłanie sygnału do procesu na podstawie PID lub nazwy.',
                    syntaxHint: 'kill 1420   LUB   pkill apache2   LUB   killall apache2',
                    hint: 'Wpisz `kill 1420`, `pkill apache2` lub `killall apache2`.',
                    validate: (cmd) => /^(kill|pkill|killall)/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj stan usługi serwera www (systemctl status apache2)',
                    ckeDesc: 'Weryfikacja czy usługa przeszła w stan inactive (dead).',
                    syntaxHint: 'systemctl status apache2',
                    hint: 'Wpisz `systemctl status apache2`.',
                    validate: (cmd) => /systemctl\s+status\s+apache2|service\s+apache2\s+status/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_archive_backup',
            title: 'Tworzenie i weryfikacja skompresowanego archiwum tar.gz',
            cat: 'inf02_admin',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 35,
            os: 'linux',
            desc: 'Utwórz archiwum kopii zapasowej katalogu /var/www skompresowane algorytmem gzip, a następnie wyświetl jego zawartość.',
            steps: [
                {
                    task: 'Utwórz skompresowane archiwum: tar -czvf backup_www.tar.gz /var/www',
                    ckeDesc: 'Tworzenie archiwum tar z kompresją gzip na potrzeby kopii bezpieczeństwa.',
                    syntaxHint: 'tar -czvf backup_www.tar.gz /var/www',
                    hint: 'Wpisz `tar -czvf backup_www.tar.gz /var/www`.',
                    validate: (cmd) => /tar\s+.*c.*z.*f.*backup_www/i.test(cmd) || /tar\s+-(czvf|czf)\s+backup_www/i.test(cmd)
                },
                {
                    task: 'Wyświetl spis plików zawartych w archiwum bez jego rozpakowywania (tar -tvf backup_www.tar.gz)',
                    ckeDesc: 'Testowanie integralności i podgląd nagłówków plików w archiwum tar.',
                    syntaxHint: 'tar -tvf backup_www.tar.gz',
                    hint: 'Wpisz `tar -tvf backup_www.tar.gz` lub `tar -ztvf backup_www.tar.gz`.',
                    validate: (cmd) => /tar\s+.*t.*f/i.test(cmd)
                },
                {
                    task: 'Sprawdź szczegóły i rozmiar utworzonego pliku archiwum (ls -lh backup_www.tar.gz)',
                    ckeDesc: 'Odczyt atrybutów pliku w czytelnym formacie jednostek (human-readable).',
                    syntaxHint: 'ls -lh backup_www.tar.gz',
                    hint: 'Wpisz `ls -lh backup_www.tar.gz`.',
                    validate: (cmd) => /ls\s+.*backup_www/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_firewall_netsh',
            title: 'Konfiguracja zapory sieciowej Windows przez netsh',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Sprawdź stan profili Windows Defender Firewall, włącz ochronę dla wszystkich profili i dodaj regułę blokującą port 8080.',
            steps: [
                {
                    task: 'Wyświetl stan profili zapory sieciowej (netsh advfirewall show allprofiles)',
                    ckeDesc: 'Odczyt stanu profili Domain, Private i Public w zaporze Windows.',
                    syntaxHint: 'netsh advfirewall show allprofiles',
                    hint: 'Wpisz `netsh advfirewall show allprofiles`.',
                    validate: (cmd) => /netsh\s+advfirewall\s+show\s+allprofiles/i.test(cmd)
                },
                {
                    task: 'Włącz zaporę dla wszystkich profili (netsh advfirewall set allprofiles state on)',
                    ckeDesc: 'Globalne uruchomienie filtrowania pakietów we wszystkich profilach sieciowych.',
                    syntaxHint: 'netsh advfirewall set allprofiles state on',
                    hint: 'Wpisz `netsh advfirewall set allprofiles state on`.',
                    validate: (cmd) => /netsh\s+advfirewall\s+set\s+allprofiles\s+state\s+on/i.test(cmd)
                },
                {
                    task: 'Dodaj regułę blokującą ruch przychodzący na porcie TCP 8080: netsh advfirewall firewall add rule name="Blokada_8080" dir=in action=block protocol=TCP localport=8080',
                    ckeDesc: 'Definiowanie reguły blokującej niestandardowy port w systemie Windows.',
                    syntaxHint: 'netsh advfirewall firewall add rule name="Blokada_8080" dir=in action=block protocol=TCP localport=8080',
                    hint: 'Wklej lub wpisz regułę dodającą wpis blokujący.',
                    validate: (cmd) => /netsh\s+advfirewall\s+firewall\s+add\s+rule.*8080/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_pipeline_cut_sort',
            title: 'Zaawansowane przetwarzanie tekstu w potokach (cut, sort, uniq)',
            cat: 'inf02_admin',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Wyodrębnij nazwy kont z pliku /etc/passwd, posortuj je alfabetycznie i zlicz liczbę nieudanych logowań w pliku auth.log.',
            steps: [
                {
                    task: 'Wyodrębnij tylko nazwy użytkowników (pierwszą kolumnę) z /etc/passwd: cat /etc/passwd | cut -d: -f1',
                    ckeDesc: 'Dzielenie tekstu separatorem dwukropka i selekcja kolumny 1 w systemie Linux.',
                    syntaxHint: 'cat /etc/passwd | cut -d: -f1   LUB   cut -d: -f1 /etc/passwd',
                    hint: 'Wpisz `cat /etc/passwd | cut -d: -f1` lub `cut -d: -f1 /etc/passwd`.',
                    validate: (cmd) => /(cat.*passwd.*\|.*cut|cut\s+-d:.*passwd)/i.test(cmd)
                },
                {
                    task: 'Posortuj nazwy użytkowników alfabetycznie: cat /etc/passwd | cut -d: -f1 | sort',
                    ckeDesc: 'Łączenie narzędzi potokiem (pipe) w celu sortowania strumienia tekstowego.',
                    syntaxHint: 'cat /etc/passwd | cut -d: -f1 | sort',
                    hint: 'Wpisz `cat /etc/passwd | cut -d: -f1 | sort`.',
                    validate: (cmd) => /cut.*\|.*sort/i.test(cmd)
                },
                {
                    task: 'Zlicz liczbę wierszy z błędnymi logowaniami w logu: grep "Failed" /var/log/auth.log | wc -l',
                    ckeDesc: 'Filtrowanie zdarzeń bezpieczeństwa i zliczanie wystąpień poleceniem wc.',
                    syntaxHint: 'grep "Failed" /var/log/auth.log | wc -l   LUB   grep -c "Failed" /var/log/auth.log',
                    hint: 'Wpisz `grep "Failed" /var/log/auth.log | wc -l` lub `grep -c "Failed" /var/log/auth.log`.',
                    validate: (cmd) => /(grep.*Failed.*wc|grep\s+-c.*Failed|grep.*auth\.log.*wc)/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf02_win_service_sc',
            title: 'Zarządzanie usługami Windows CMD (sc & net)',
            cat: 'inf02_admin',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★☆☆',
            xp: 25,
            os: 'windows',
            desc: 'Sprawdź stan usługi World Wide Web (w3svc), zatrzymaj ją poleceniem net stop i ustaw tryb uruchamiania na automatyczny.',
            steps: [
                {
                    task: 'Wyświetl stan usługi serwera WWW: sc query w3svc',
                    ckeDesc: 'Odpytanie menedżera usług Service Control o stan usługi w3svc.',
                    syntaxHint: 'sc query w3svc',
                    hint: 'Wpisz `sc query w3svc`.',
                    validate: (cmd) => /sc\s+query\s+w3svc/i.test(cmd)
                },
                {
                    task: 'Zatrzymaj usługę serwera WWW: net stop w3svc (lub sc stop w3svc)',
                    ckeDesc: 'Wstrzymanie działania usługi sieciowej w Windows.',
                    syntaxHint: 'net stop w3svc   LUB   sc stop w3svc',
                    hint: 'Wpisz `net stop w3svc` lub `sc stop w3svc`.',
                    validate: (cmd) => /(net|sc)\s+stop\s+w3svc/i.test(cmd)
                },
                {
                    task: 'Skonfiguruj automatyczny start usługi przy rozruchu: sc config w3svc start= auto',
                    ckeDesc: 'Modyfikacja typu uruchomienia usługi w rejestrze systemu Windows.',
                    syntaxHint: 'sc config w3svc start= auto',
                    hint: 'Wpisz `sc config w3svc start= auto` (zwróć uwagę na spację po znaku równości).',
                    validate: (cmd) => /sc\s+config\s+w3svc.*start=\s*auto/i.test(cmd)
                }
            ]
        },
        {
            id: 'inf08_network_recon_ss',
            title: 'Audyt aktywnych gniazd sieciowych i otwartych portów serwera',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Bezpieczeństwo',
            badgeColor: 'danger',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Przeanalizuj nasłuchujące porty TCP/UDP za pomocą narzędzia ss/netstat i przeprowadź skanowanie usług nmap.',
            steps: [
                {
                    task: 'Wyświetl wszystkie nasłuchujące porty TCP i UDP wraz z numerami portów (ss -tuln / netstat -tuln)',
                    ckeDesc: 'Identyfikacja otwartych gniazd sieciowych i demonów nasłuchujących w systemie Linux.',
                    syntaxHint: 'ss -tuln   LUB   netstat -tuln',
                    hint: 'Wpisz `ss -tuln` lub `netstat -tuln` lub `ss -tlpn`.',
                    validate: (cmd) => /^(ss|netstat)/i.test(cmd)
                },
                {
                    task: 'Wykonaj skanowanie lokalnych usług i wersji za pomocą nmap: nmap -sV 192.168.1.100',
                    ckeDesc: 'Skanowanie portów i rozpoznawanie bannerów wersji usług serwerowych.',
                    syntaxHint: 'nmap -sV 192.168.1.100   LUB   nmap 192.168.1.100',
                    hint: 'Wpisz `nmap -sV 192.168.1.100` lub `nmap localhost`.',
                    validate: (cmd) => /^nmap/i.test(cmd)
                },
                {
                    task: 'Zweryfikuj dostępność i nagłówki serwera HTTP na porcie 80 za pomocą curl: curl -I http://192.168.1.100',
                    ckeDesc: 'Odpytanie serwera o nagłówki odpowiedzi protokołu HTTP/1.1.',
                    syntaxHint: 'curl -I http://192.168.1.100   LUB   curl -i http://localhost',
                    hint: 'Wpisz `curl -I http://192.168.1.100` lub `curl -i http://localhost`.',
                    validate: (cmd) => /curl\s+-(I|i)/i.test(cmd) || /curl\s+http/i.test(cmd)
                }
            ]
        }
    ];

    // ════════════════════════════════════════════════════════════════════════════
    // 8. TERMINAL SIMULATOR CONTROLLER & UI GLUE
    // ════════════════════════════════════════════════════════════════════════════

    class TerminalSimulator {
        constructor() {
            this.vfs = new VirtualFileSystem();
            this.net = new NetworkState();
            this.currentOs = 'linux'; // 'linux' or 'windows'
            this.currentSubShell = null;
            this.subShellEngine = null;

            this.commandHistory = [];
            this.historyIndex = -1;

            this.activeScenario = null;
            this.activeScenarioStep = 0;
            this.scenarioGuideCollapsed = localStorage.getItem('zsem_cli_guide_collapsed') === 'true';

            this.outputEl = document.getElementById('termOutput');
            this.inputEl = document.getElementById('termInput');
            this.promptLabel = document.getElementById('termPromptLabel');
            this.windowEl = document.getElementById('terminalWindow');

            this.init();
        }

        init() {
            this.bindEvents();
            const savedTheme = localStorage.getItem('zsem_cli_theme');
            if (savedTheme) this.applyTheme(savedTheme);
            this.renderWelcome();
            this.updatePrompt();
            this.updateStatsUI();
            this.renderScenarios();
            this.renderServices();
            this.renderCheatSheet();
            this.initSessionTimer();
        }

        bindEvents() {
            // Enter command
            this.inputEl.addEventListener('keydown', (e) => this.handleKeyDown(e));

            // OS Toggles
            document.getElementById('osBtnLinux')?.addEventListener('click', () => this.switchOs('linux'));
            document.getElementById('osBtnWin')?.addEventListener('click', () => this.switchOs('windows'));

            // Tool Buttons
            document.getElementById('btnTermClear')?.addEventListener('click', () => this.clearScreen());
            document.getElementById('btnTermCopy')?.addEventListener('click', () => this.copyOutput());
            document.getElementById('btnTermExport')?.addEventListener('click', () => this.exportLog());
            document.getElementById('btnTermFullscreen')?.addEventListener('click', () => this.toggleFullscreen());
            document.getElementById('dotClose')?.addEventListener('click', () => this.clearScreen());
            document.getElementById('dotMax')?.addEventListener('click', () => this.toggleFullscreen());
            document.getElementById('dotMin')?.addEventListener('click', () => this.clearScreen());

            // Nano Save/Exit Keys
            document.getElementById('nanoTextarea')?.addEventListener('keydown', (e) => this.handleNanoKeyDown(e));

            // Virtual Touch Keys
            document.querySelectorAll('.touch-key').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key;
                    const insert = btn.dataset.insert;
                    if (insert) {
                        this.inputEl.value += insert;
                        this.inputEl.focus();
                    } else if (key === 'CtrlC') {
                        this.writeLine('^C', 'warn');
                        this.inputEl.value = '';
                    } else if (key === 'CtrlL') {
                        this.clearScreen();
                    } else if (key === 'Up') {
                        this.navigateHistory(-1);
                    } else if (key === 'Down') {
                        this.navigateHistory(1);
                    } else if (key === 'Tab') {
                        this.autoComplete();
                    }
                });
            });

            // Category filter chips
            document.querySelectorAll('#scenarioCategoryChips .cat-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('#scenarioCategoryChips .cat-chip').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    this.filterScenarios(chip.dataset.cat || 'all');
                });
            });

            // Reset VFS Button
            document.getElementById('scenarioResetVfsBtn')?.addEventListener('click', () => {
                this.vfs.reset();
                this.net.reset();
                this.writeLine('Wirtualny system plików (VFS) oraz konfiguracja sieci zostały zresetowane.', 'info');
                this.updatePrompt();
                this.updateStatsUI();
                this.renderServices();
            });

            // Theme selection
            document.querySelectorAll('.term-theme-opt').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.querySelectorAll('.term-theme-opt').forEach(o => o.classList.remove('active'));
                    opt.classList.add('active');
                    const th = opt.dataset.theme;
                    this.applyTheme(th);
                });
            });

            // Terminal Tabs
            document.querySelectorAll('#terminalTabs .term-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('#terminalTabs .term-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    this.switchTab(tab.dataset.tab);
                });
            });

            // Search in history
            document.getElementById('btnTermSearch')?.addEventListener('click', () => this.promptHistorySearch());

            // Scenario Skip / Clear
            document.getElementById('scenarioSkipBtn')?.addEventListener('click', () => this.skipScenarioStep());
            document.getElementById('scenarioClearBtn')?.addEventListener('click', () => this.clearScreen());
        }

        applyTheme(theme) {
            this.windowEl?.classList.remove('theme-ubuntu', 'theme-powershell', 'theme-dracula', 'theme-matrix');
            if (theme && theme !== 'default') {
                this.windowEl?.classList.add(`theme-${theme}`);
            }
            try { localStorage.setItem('zsem_cli_theme', theme || 'default'); } catch (e) {}
        }

        switchTab(tabKey) {
            if (tabKey === 'tab1') {
                this.net.state.currentUserLinux = 'student';
                this.updatePrompt();
                this.writeLine('\n[ Przełączono na sesję użytkownika student ]', 'term-dim');
            } else if (tabKey === 'tab2') {
                this.net.state.currentUserLinux = 'root';
                this.updatePrompt();
                this.writeLine('\n[ Przełączono na sesję uprzywilejowaną root ]', 'warn');
            } else if (tabKey === 'tab3') {
                this.openNanoEditor('konfiguracja.conf');
            }
        }

        promptHistorySearch() {
            if (!this.commandHistory.length) {
                this.writeLine('Historia poleceń jest pusta.', 'warn');
                return;
            }
            this.writeLine('\n🔍 Ostatnie polecenia w historii sesji:', 'term-cyan');
            const recent = this.commandHistory.slice(-10);
            recent.forEach((cmd, idx) => {
                this.writeLine(`  [${idx + 1}] ${cmd}`, 'term-dim');
            });
            this.writeLine('Wskazówka: Używaj strzałek w górę / w dół na klawiaturze, aby przewijać historię.\n', 'term-dim');
        }

        renderWelcome() {
            this.clearScreen();
            if (this.currentOs === 'linux') {
                this.writeLine('╔═══════════════════════════════════════════════════════════════════════════╗', 'term-cyan');
                this.writeLine('║  ZSEM Linux Terminal Simulator v2.5 (Ubuntu 22.04 LTS / GNU Bash 5.1)    ║', 'term-cyan');
                this.writeLine('║  Wbudowany VFS, serwery Apache2, BIND9, Samba, Postfix, MySQL & Nano.     ║', 'term-cyan');
                this.writeLine('║  Wpisz "help", "man <komenda>" lub wybierz zadanie CKE z prawego panelu. ║', 'term-cyan');
                this.writeLine('╚═══════════════════════════════════════════════════════════════════════════╝', 'term-cyan');
                this.writeLine('Zalogowano jako: student@zsem-lab (UID=1000, GID=1000). Katalog domowy: /home/student\n', 'term-dim');
            } else {
                this.writeLine('Microsoft Windows [Version 10.0.19045.3636]', 'term-dim');
                this.writeLine('(c) Microsoft Corporation. All rights reserved.\n', 'term-dim');
                this.writeLine('ZSEM Windows Lab: Obsługa CMD, PowerShell 7, DiskPart, netsh i narzędzi CKE.', 'term-yellow');
                this.writeLine('Wpisz "powershell", "diskpart", "ipconfig /all", "help" lub wybierz zadanie.\n', 'term-dim');
            }
        }

        switchOs(os) {
            this.currentOs = os;
            this.currentSubShell = null;
            this.subShellEngine = null;

            const btnL = document.getElementById('osBtnLinux');
            const btnW = document.getElementById('osBtnWin');
            const titleLabel = document.getElementById('termTitleLabel');

            if (os === 'linux') {
                btnL?.classList.add('active');
                btnW?.classList.remove('active');
                if (titleLabel) titleLabel.textContent = 'bash — student@zsem-lab: ~';
            } else {
                btnW?.classList.add('active');
                btnL?.classList.remove('active');
                if (titleLabel) titleLabel.textContent = 'Command Prompt — cmd.exe (ZSEM-STUDENT)';
            }

            this.renderWelcome();
            this.updatePrompt();
            this.updateStatsUI();
        }

        updatePrompt() {
            if (this.currentSubShell) {
                if (this.currentSubShell === 'powershell') {
                    this.promptLabel.textContent = `PS ${this.vfs.currentDirWin}> `;
                    this.promptLabel.style.color = '#38bdf8';
                } else if (this.currentSubShell === 'diskpart') {
                    this.promptLabel.textContent = 'DISKPART> ';
                    this.promptLabel.style.color = '#f59e0b';
                } else if (this.currentSubShell === 'mysql') {
                    this.promptLabel.textContent = 'mysql> ';
                    this.promptLabel.style.color = '#38bdf8';
                } else if (this.currentSubShell === 'nslookup') {
                    this.promptLabel.textContent = '> ';
                    this.promptLabel.style.color = '#94a3b8';
                } else if (this.currentSubShell === 'python') {
                    this.promptLabel.textContent = '>>> ';
                    this.promptLabel.style.color = '#34d399';
                }
                return;
            }

            if (this.currentOs === 'linux') {
                const isRoot = this.net.state.currentUserLinux === 'root';
                const user = this.net.state.currentUserLinux;
                const host = this.net.state.hostname;
                const dir = this.vfs.currentDirLinux.replace('/home/student', '~');
                this.promptLabel.textContent = `${user}@${host}:${dir}${isRoot ? '#' : '$'} `;
                this.promptLabel.style.color = isRoot ? '#ef4444' : '#3fb950';
            } else {
                this.promptLabel.textContent = `${this.vfs.currentDirWin}>`;
                this.promptLabel.style.color = '#e2e8f0';
            }
        }

        handleKeyDown(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const cmd = this.inputEl.value;
                this.inputEl.value = '';
                this.executeCommand(cmd);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateHistory(-1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateHistory(1);
            } else if (e.key === 'Tab') {
                e.preventDefault();
                this.autoComplete();
            } else if (e.ctrlKey && e.key.toLowerCase() === 'l') {
                e.preventDefault();
                this.clearScreen();
            } else if (e.ctrlKey && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                this.writeLine(this.promptLabel.textContent + this.inputEl.value + ' ^C', 'warn');
                this.inputEl.value = '';
            }
        }

        executeCommand(rawCmd) {
            const trimmed = rawCmd.trim();
            if (!trimmed) {
                this.writeLine(this.promptLabel.textContent, 'term-dim');
                return;
            }

            this.commandHistory.push(rawCmd);
            this.historyIndex = this.commandHistory.length;
            this.net.state.stats.commandsExecuted++;
            this.net.save();
            this.updateStatsUI();

            this.writeLine(`${this.promptLabel.textContent}${rawCmd}`, 'term-cmd');

            // Check sub-shell execution
            if (this.currentSubShell && this.subShellEngine) {
                const res = this.subShellEngine.execute(trimmed);
                if (res.action === 'exit') {
                    this.currentSubShell = null;
                    this.subShellEngine = null;
                    this.updatePrompt();
                } else if (res.action === 'clear') {
                    this.clearScreen();
                } else if (res.output) {
                    this.writeOutput(res.output);
                }
                this.validateScenario(trimmed);
                return;
            }

            // Pipeling handling (e.g. `cat file | grep text | wc -l`)
            if (trimmed.includes('|')) {
                const parts = trimmed.split('|').map(p => p.trim());
                let pipeInput = '';
                for (const part of parts) {
                    const parsed = this.parseArgs(part);
                    const cmdName = parsed[0]?.toLowerCase();
                    const cmdArgs = parsed.slice(1);
                    const handler = this.currentOs === 'linux' ? LINUX_COMMANDS[cmdName] : WINDOWS_COMMANDS[cmdName];
                    if (handler) {
                        pipeInput = handler(cmdArgs, this, pipeInput);
                    } else {
                        pipeInput = `Command not found: ${cmdName}`;
                        break;
                    }
                }
                this.writeOutput(pipeInput);
                this.validateScenario(trimmed);
                return;
            }

            // Output redirection (> or >>)
            if (trimmed.includes('>') || trimmed.includes('>>')) {
                const isAppend = trimmed.includes('>>');
                const parts = trimmed.split(isAppend ? '>>' : '>');
                const commandPart = parts[0].trim();
                const fileTarget = parts[1].trim();

                const parsed = this.parseArgs(commandPart);
                const cmdName = parsed[0]?.toLowerCase();
                const cmdArgs = parsed.slice(1);
                const handler = this.currentOs === 'linux' ? LINUX_COMMANDS[cmdName] : WINDOWS_COMMANDS[cmdName];

                if (handler) {
                    const result = handler(cmdArgs, this);
                    if (result && result !== '__CLEAR__') {
                        if (isAppend) {
                            const existing = this.vfs.getNode(fileTarget, this.currentOs === 'windows')?.content || '';
                            this.vfs.createFile(fileTarget, existing + '\n' + result, this.currentOs === 'windows');
                        } else {
                            this.vfs.createFile(fileTarget, result, this.currentOs === 'windows');
                        }
                    }
                }
                this.validateScenario(trimmed);
                return;
            }

            const parsed = this.parseArgs(trimmed);
            const cmdName = parsed[0]?.toLowerCase();
            const cmdArgs = parsed.slice(1);

            const handler = this.currentOs === 'linux' ? LINUX_COMMANDS[cmdName] : WINDOWS_COMMANDS[cmdName];

            if (handler) {
                const output = handler(cmdArgs, this);
                if (output === '__CLEAR__') {
                    this.clearScreen();
                } else if (output !== undefined && output !== '') {
                    this.writeOutput(output);
                }
            } else {
                if (this.currentOs === 'linux') {
                    this.writeLine(`bash: ${cmdName}: command not found. Wpisz 'man' lub 'help' aby zobaczyć listę dostępnych poleceń.`, 'error');
                } else {
                    this.writeLine(`'${cmdName}' is not recognized as an internal or external command,\r\noperable program or batch file. Type 'help' for available commands.\r\n`, 'error');
                }
            }

            this.updatePrompt();
            this.renderServices();
            this.validateScenario(trimmed);
        }

        parseArgs(cmdStr) {
            const args = [];
            let current = '';
            let inQuotes = false;
            let quoteChar = '';

            for (let i = 0; i < cmdStr.length; i++) {
                const c = cmdStr[i];
                if ((c === '"' || c === "'") && !inQuotes) {
                    inQuotes = true;
                    quoteChar = c;
                } else if (c === quoteChar && inQuotes) {
                    inQuotes = false;
                    quoteChar = '';
                } else if (c === ' ' && !inQuotes) {
                    if (current.length) {
                        args.push(current);
                        current = '';
                    }
                } else {
                    current += c;
                }
            }
            if (current.length) args.push(current);
            return args;
        }

        writeLine(text, type = 'normal') {
            const p = document.createElement('div');
            p.className = `term-line ${type ? 'term-' + type : ''}`;
            p.textContent = text;
            this.outputEl.appendChild(p);
            this.scrollToBottom();
        }

        writeOutput(text) {
            const lines = String(text).split('\n');
            lines.forEach(l => {
                const p = document.createElement('div');
                p.className = 'term-line';
                p.textContent = l.replace(/\r$/, '');
                this.outputEl.appendChild(p);
            });
            this.scrollToBottom();
        }

        clearScreen() {
            this.outputEl.innerHTML = '';
            this.scrollToBottom();
        }

        scrollToBottom() {
            this.outputEl.scrollTop = this.outputEl.scrollHeight;
        }

        navigateHistory(dir) {
            if (!this.commandHistory.length) return;
            this.historyIndex += dir;
            if (this.historyIndex < 0) this.historyIndex = 0;
            if (this.historyIndex >= this.commandHistory.length) {
                this.historyIndex = this.commandHistory.length;
                this.inputEl.value = '';
                return;
            }
            this.inputEl.value = this.commandHistory[this.historyIndex] || '';
        }

        autoComplete() {
            const cur = this.inputEl.value;
            const parts = cur.split(' ');
            const last = parts[parts.length - 1];
            if (!last) return;

            let pool = [];
            if (this.currentSubShell === 'powershell') {
                pool = ['Get-Service', 'Start-Service', 'Stop-Service', 'Restart-Service', 'Get-Process', 'Stop-Process', 'Get-NetIPAddress', 'New-NetIPAddress', 'Test-NetConnection', 'Get-NetFirewallRule', 'New-NetFirewallRule', 'Get-Disk', 'Initialize-Disk', 'New-Partition', 'Format-Volume', 'Get-LocalUser', 'New-LocalUser', 'Add-LocalGroupMember', 'Install-WindowsFeature', 'Get-WindowsFeature', 'Where-Object', 'Select-Object', 'Measure-Object', 'exit', 'clear'];
            } else if (this.currentSubShell === 'diskpart') {
                pool = ['list disk', 'select disk', 'detail disk', 'list partition', 'select partition', 'create partition primary', 'format', 'assign', 'active', 'shrink', 'extend', 'convert', 'clean', 'list volume', 'select volume', 'detail volume', 'exit', 'help', 'cls'];
            } else if (this.currentSubShell === 'mysql') {
                pool = ['SHOW DATABASES;', 'USE', 'SHOW TABLES;', 'SELECT', 'INSERT INTO', 'UPDATE', 'DELETE FROM', 'CREATE DATABASE', 'CREATE TABLE', 'ALTER TABLE', 'DROP TABLE', 'DESCRIBE', 'GRANT', 'FLUSH PRIVILEGES;', 'status;', 'help;', 'exit;', 'quit;'];
            } else {
                pool = this.currentOs === 'linux' ? Object.keys(LINUX_COMMANDS) : Object.keys(WINDOWS_COMMANDS);
            }

            const matches = pool.filter(c => c.toLowerCase().startsWith(last.toLowerCase()));
            if (matches.length === 1) {
                parts[parts.length - 1] = matches[0];
                this.inputEl.value = parts.join(' ') + ' ';
            } else if (matches.length > 1) {
                this.writeLine(matches.join('   '), 'term-dim');
            }
        }

        copyOutput() {
            navigator.clipboard.writeText(this.outputEl.innerText).then(() => {
                this.showToast('Skopiowano całą zawartość konsoli do schowka!');
            });
        }

        exportLog() {
            const blob = new Blob([this.outputEl.innerText], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `zsem_cli_session_${Date.now()}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            this.showToast('Wyeksportowano log sesji terminala!');
        }

        toggleFullscreen() {
            this.windowEl.classList.toggle('fullscreen');
            document.body.classList.toggle('overflow-hidden', this.windowEl.classList.contains('fullscreen'));
            this.inputEl.focus();
        }

        // ── GNU Nano Editor ──────────────────────────────────────────────────

        openNanoEditor(filename) {
            const isWin = this.currentOs === 'windows';
            const node = this.vfs.getNode(filename, isWin);
            const overlay = document.getElementById('nanoOverlay');
            const textarea = document.getElementById('nanoTextarea');
            const nameLabel = document.getElementById('nanoFilename');

            this.editingFile = filename;
            if (nameLabel) nameLabel.textContent = `Plik: ${filename}`;
            if (textarea) {
                textarea.value = node ? (node.content || '') : '';
                textarea.focus();
            }
            if (overlay) overlay.style.display = 'flex';
        }

        handleNanoKeyDown(e) {
            if (e.ctrlKey && e.key.toLowerCase() === 'o') {
                e.preventDefault();
                const content = document.getElementById('nanoTextarea')?.value || '';
                this.vfs.createFile(this.editingFile, content, this.currentOs === 'windows');
                const status = document.getElementById('nanoStatusMsg');
                if (status) status.textContent = `[ Zapisano ${content.length} bajtów do pliku '${this.editingFile}' ]`;
            } else if (e.ctrlKey && e.key.toLowerCase() === 'x') {
                e.preventDefault();
                const overlay = document.getElementById('nanoOverlay');
                if (overlay) overlay.style.display = 'none';
                this.inputEl.focus();
                this.writeLine(`[ Zamknięto edytor nano ]`, 'term-dim');
                this.updatePrompt();
            }
        }

        // ════════════════════════════════════════════════════════════════════════
        // SCENARIOS & STEP-BY-STEP PEDAGOGICAL GUIDE CONTROLLER
        // ════════════════════════════════════════════════════════════════════════

        renderScenarios() {
            const list = document.getElementById('scenarioList');
            if (!list) return;

            const completed = window.CLI_LAB_USER?.completedScenarios || [];
            list.innerHTML = CKE_SCENARIOS.map(sc => {
                const isComp = completed.includes(sc.id);
                const isAct = this.activeScenario && this.activeScenario.id === sc.id;
                return `
                    <div class="scenario-card-item ${isAct ? 'active' : ''} ${isComp ? 'completed' : ''}" data-id="${sc.id}" onclick="window.zsemTerminal && window.zsemTerminal.selectScenarioById('${sc.id}')">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-${sc.badgeColor || 'primary'} bg-opacity-20 text-${sc.badgeColor || 'primary'} fw-bold" style="font-size:.7rem;">${sc.catLabel || 'CKE'}</span>
                            <div class="d-flex align-items-center gap-1">
                                <span class="text-warning small" style="font-size:.7rem;">${sc.stars || '★☆☆'}</span>
                                <span class="badge bg-warning bg-opacity-15 text-warning" style="font-size:.65rem;">+${sc.xp || 25} XP</span>
                                ${isComp ? '<i class="bi bi-check-circle-fill text-success" title="Zaliczone"></i>' : ''}
                            </div>
                        </div>
                        <div class="fw-bold text-dark dark:text-light" style="font-size:.82rem;">${sc.title}</div>
                        <div class="text-muted text-truncate mt-1" style="font-size:.72rem;">${sc.desc}</div>
                    </div>
                `;
            }).join('');
        }

        filterScenarios(cat) {
            const cards = document.querySelectorAll('#scenarioList .scenario-card-item');
            cards.forEach(c => {
                const sc = CKE_SCENARIOS.find(s => s.id === c.dataset.id);
                if (cat === 'all' || (cat === 'windows' && sc?.os === 'windows') || sc?.cat === cat || (cat === 'inf02_sys' && (sc?.cat === 'inf02_sys' || sc?.cat === 'inf02_admin'))) {
                    c.style.display = 'block';
                } else {
                    c.style.display = 'none';
                }
            });
        }

        selectScenarioById(id) {
            const sc = CKE_SCENARIOS.find(s => s.id === id);
            if (sc) this.selectScenario(sc);
        }

        selectScenario(sc) {
            this.activeScenario = sc;
            this.activeScenarioStep = 0;
            if (sc.os !== 'any' && sc.os !== this.currentOs) {
                this.switchOs(sc.os);
            }
            this.renderScenarios();

            const wrapper = document.getElementById('scenarioGuideWrapper');
            if (wrapper) wrapper.style.display = 'block';

            this.updateScenarioStepUI();
            this.applyScenarioGuideCollapseState();

            this.writeLine(`\n🎯 [ROZPOCZĘTO ZADANIE CKE]: ${sc.title}`, 'term-cyan');
            this.writeLine(`Opis: ${sc.desc}\n`, 'term-dim');
        }

        updateScenarioStepUI() {
            if (!this.activeScenario) return;
            const sc = this.activeScenario;
            const total = sc.steps.length;
            const cur = this.activeScenarioStep;
            const step = sc.steps[cur];
            const pct = Math.round((cur / total) * 100);

            // Update Badges & Titles
            const catBadge = document.getElementById('scenarioCatBadge');
            const xpBadge = document.getElementById('scenarioXpBadge');
            const starsBadge = document.getElementById('scenarioStars');
            const progLabel = document.getElementById('scenarioProgressLabel');
            const stepLabel = document.getElementById('scenarioStepLabel');
            const progressBar = document.getElementById('scenarioProgressBar');

            if (catBadge) catBadge.textContent = sc.catLabel || 'INF.02';
            if (xpBadge) xpBadge.innerHTML = `<i class="bi bi-trophy-fill me-1"></i>+${sc.xp || 25} XP`;
            if (starsBadge) starsBadge.textContent = sc.stars || '★★★';
            if (progLabel) progLabel.textContent = sc.title;
            if (stepLabel) stepLabel.textContent = `Krok ${cur + 1}/${total}`;
            if (progressBar) progressBar.style.width = `${pct}%`;

            // Update Mini Bar
            const miniStepBadge = document.getElementById('miniStepBadge');
            const miniTitleLabel = document.getElementById('miniTitleLabel');
            const miniSnippet = document.getElementById('miniInstructionSnippet');
            if (miniStepBadge) miniStepBadge.textContent = `Krok ${cur + 1}/${total}`;
            if (miniTitleLabel) miniTitleLabel.textContent = sc.title;
            if (miniSnippet) miniSnippet.textContent = `— ${step?.task || 'Zadanie ukończone!'}`;

            // Update Step Instructions Box
            const instrEl = document.getElementById('scenarioStepInstruction');
            const ckeDescEl = document.getElementById('scenarioStepCkeDesc');
            const cmdEl = document.getElementById('scenarioSuggestedCmd');
            const hintEl = document.getElementById('scenarioHintText');

            if (instrEl) instrEl.textContent = step?.task || 'Zadanie zostało pomyślnie ukończone!';
            if (ckeDescEl) ckeDescEl.textContent = step?.ckeDesc || sc.desc;
            if (cmdEl) cmdEl.textContent = step?.syntaxHint || 'Brak sugerowanego polecenia';
            if (hintEl) hintEl.textContent = step?.hint || 'Wykonaj polecenie zgodnie z poleceniem zadania egzaminacyjnego.';

            // Right panel snippet update
            const descRight = document.getElementById('activeScenarioDesc');
            if (descRight) {
                descRight.innerHTML = `<strong>Zadanie:</strong> ${sc.desc}<br><strong class="text-primary mt-2 d-block">Krok ${cur + 1}/${total}:</strong> ${step?.task || 'Ukończono!'}`;
            }
        }

        toggleScenarioGuide(forceShow = null) {
            if (forceShow !== null) {
                this.scenarioGuideCollapsed = !forceShow;
            } else {
                this.scenarioGuideCollapsed = !this.scenarioGuideCollapsed;
            }
            localStorage.setItem('zsem_cli_guide_collapsed', this.scenarioGuideCollapsed ? 'true' : 'false');
            this.applyScenarioGuideCollapseState();
        }

        applyScenarioGuideCollapseState() {
            const miniBar = document.getElementById('scenarioMiniBar');
            const fullCard = document.getElementById('scenarioProgressWrap');
            if (this.scenarioGuideCollapsed) {
                if (miniBar) miniBar.style.display = 'flex';
                if (fullCard) fullCard.style.display = 'none';
            } else {
                if (miniBar) miniBar.style.display = 'none';
                if (fullCard) fullCard.style.display = 'block';
            }
        }

        toggleScenarioHint() {
            const hintBox = document.getElementById('scenarioHintBox');
            if (hintBox) {
                hintBox.style.display = hintBox.style.display === 'none' ? 'block' : 'none';
            }
        }

        pasteCurrentStepCmd() {
            if (!this.activeScenario) return;
            const step = this.activeScenario.steps[this.activeScenarioStep];
            if (step && step.syntaxHint) {
                const clean = step.syntaxHint.split('   LUB   ')[0].trim();
                this.inputEl.value = clean;
                this.inputEl.focus();
                this.showToast(`Wklejono polecenie: ${clean}`);
            }
        }

        skipScenarioStep() {
            if (!this.activeScenario) return;
            const step = this.activeScenario.steps[this.activeScenarioStep];
            this.writeLine(`⏩ Pominięto krok ${this.activeScenarioStep + 1}: ${step?.task}`, 'warn');
            this.activeScenarioStep++;
            if (this.activeScenarioStep >= this.activeScenario.steps.length) {
                this.completeActiveScenario();
            } else {
                this.updateScenarioStepUI();
            }
        }

        validateScenario(cmd) {
            if (!this.activeScenario) return;
            const sc = this.activeScenario;
            const step = sc.steps[this.activeScenarioStep];
            if (!step) return;

            const isValid = step.validate(cmd, this.currentOs, this.vfs, this.net);
            if (isValid) {
                this.writeLine(`✔ [ZALICZONO KROK ${this.activeScenarioStep + 1}/${sc.steps.length}]: ${step.task}`, 'success');
                this.activeScenarioStep++;
                if (this.activeScenarioStep >= sc.steps.length) {
                    this.completeActiveScenario();
                } else {
                    this.updateScenarioStepUI();
                }
            }
        }

        completeActiveScenario() {
            const sc = this.activeScenario;
            sc.completed = true;
            this.writeLine(`\n🎉 GRATULACJE! Zadanie '${sc.title}' zostało ukończone w 100%!`, 'success');
            this.showToast(`Ukończono zadanie: ${sc.title}! (+${sc.xp || 25} XP)`);

            const progressBar = document.getElementById('scenarioProgressBar');
            if (progressBar) progressBar.style.width = '100%';

            this.renderScenarios();
            this.updateStatsUI();
            this.awardScenarioXp(sc);
        }

        async awardScenarioXp(sc) {
            if (!window.CLI_LAB_USER || !window.CLI_LAB_USER.csrfToken) return;

            try {
                const formData = new FormData();
                formData.append('scenario_id', sc.id);
                formData.append('os', sc.os || this.currentOs);
                formData.append('csrf_token', window.CLI_LAB_USER.csrfToken);

                const res = await fetch('../actions/cli_lab_reward.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    if (data.xp_awarded > 0) {
                        this.showToast(`Zdobyto +${data.xp_awarded} XP! Aktualny poziom: ${data.total_xp} XP`);
                        const statusXp = document.getElementById('statusXp');
                        const statUserXp = document.getElementById('statUserXp');
                        if (statusXp) statusXp.textContent = String(data.total_xp);
                        if (statUserXp) statUserXp.textContent = String(data.total_xp);
                    }
                    if (data.achievement) {
                        this.showToast(`🏆 Nowe Osiągnięcie: ${data.achievement.title}!`);
                    }
                }
            } catch (e) {
                console.warn('XP reward dispatch failed:', e);
            }
        }

        updateStatsUI() {
            const st = this.net.state;
            const comp = window.CLI_LAB_USER?.completedScenarios?.length || 0;

            const cmdCount = document.getElementById('statCmdCount');
            const scenCount = document.getElementById('statScenCount');
            const pkgCount = document.getElementById('statPkgCount');
            const timeCount = document.getElementById('statTimeCount');
            const userIp = document.getElementById('statusIp');
            const userGw = document.getElementById('statusGw');
            const userStatus = document.getElementById('statusUser');
            const pwdStatus = document.getElementById('statusPwd');

            if (cmdCount) cmdCount.textContent = String(st.stats.commandsExecuted);
            if (scenCount) scenCount.textContent = `${comp}/${CKE_SCENARIOS.length}`;
            if (pkgCount) pkgCount.textContent = String(st.installedPackages.length);
            if (timeCount) timeCount.textContent = `${st.stats.sessionMinutes}m`;

            if (userIp) userIp.textContent = st.ip;
            if (userGw) userGw.textContent = st.gateway;
            if (userStatus) userStatus.textContent = this.currentOs === 'linux' ? st.currentUserLinux : st.currentUserWin;
            if (pwdStatus) pwdStatus.textContent = this.currentOs === 'linux' ? this.vfs.currentDirLinux : this.vfs.currentDirWin;
        }

        renderServices() {
            const grid = document.getElementById('serviceMonitorGrid');
            if (!grid) return;
            const svcs = this.net.state.services;
            grid.innerHTML = Object.entries(svcs).map(([k, v]) => {
                const isRunning = v.status === 'RUNNING' || v.status.includes('RUNNING');
                return `
                    <div class="service-pill ${isRunning ? 'active' : 'inactive'}">
                        <span class="srv-name">${k}</span>
                        <span class="srv-status">${isRunning ? 'RUNNING' : 'STOPPED'}</span>
                    </div>
                `;
            }).join('');
        }

        renderCheatSheet() {
            const container = document.getElementById('commandList');
            if (!container) return;
            const cmds = [
                'apt install <pakiet>', 'systemctl status <usługa>', 'nano <plik>', 'ip a', 'ping <host>',
                'nslookup <domena>', 'a2ensite <witryna>', 'testparm', 'smbpasswd -a <user>', 'ufw enable',
                'iptables -L', 'chmod 750 <plik>', 'chown <u:g> <plik>', 'tar -czvf <plik.tar.gz>', 'crontab -l',
                'powershell', 'Get-Service', 'Get-NetIPAddress', 'Test-NetConnection', 'diskpart',
                'ipconfig /all', 'net user <user> /add', 'net share', 'icacls <plik> /grant', 'sfc /scannow'
            ];
            container.innerHTML = cmds.map(c => `<code class="px-1.5 py-0.5 rounded bg-body-secondary me-1 mb-1 d-inline-block font-monospace cursor-pointer" onclick="if(window.zsemTerminal){window.zsemTerminal.inputEl.value='${c}';window.zsemTerminal.inputEl.focus();}" title="Kliknij, aby wkleić">${c}</code>`).join(' ');
        }

        initSessionTimer() {
            setInterval(() => {
                this.net.state.stats.sessionMinutes++;
                this.updateStatsUI();
            }, 60000);
        }

        showToast(msg) {
            const container = document.getElementById('cliToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'cli-toast';
            toast.innerHTML = `<i class="bi bi-info-circle-fill text-info"></i><span>${msg}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.zsemTerminal = new TerminalSimulator();
    });

}());
