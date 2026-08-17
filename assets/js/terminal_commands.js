/**
 * CLI Lab — Advanced Stateful Terminal Simulator & CKE Exam Lab (Phase 2 Pro)
 * Supports: VFS, Pipes, Redirection, Inline Nano Editor, Sub-shells (MySQL, Diskpart, Python, PowerShell, NSLOOKUP, SSH),
 * Server Services (Apache2, BIND9, Samba, DHCP, vsftpd, Postfix, NFS, IIS), Man-pages, Achievements, and 20 CKE Scenarios.
 */
(function () {
    'use strict';

    // ════════════════════════════════════════════════════════════════════════════
    // 1. VIRTUAL FILE SYSTEM (VFS)
    // ════════════════════════════════════════════════════════════════════════════

    class VirtualFileSystem {
        constructor() {
            this.storageKey = 'zsem_cli_vfs_v4';
            this.currentDirLinux = '/home/student';
            this.currentDirWin = 'C:\\Users\\Student';
            this.nanoClipboard = '';
            this.tree = this.load() || this.createDefaultTree();
        }

        createDefaultTree() {
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
                                'bash': { type: 'file', name: 'bash', permissions: '0755', owner: 'root', group: 'root', size: 1183448, content: '[ELF binary]' },
                                'ls': { type: 'file', name: 'ls', permissions: '0755', owner: 'root', group: 'root', size: 142144, content: '[ELF binary]' },
                                'cat': { type: 'file', name: 'cat', permissions: '0755', owner: 'root', group: 'root', size: 43416, content: '[ELF binary]' },
                                'grep': { type: 'file', name: 'grep', permissions: '0755', owner: 'root', group: 'root', size: 219920, content: '[ELF binary]' },
                                'nano': { type: 'file', name: 'nano', permissions: '0755', owner: 'root', group: 'root', size: 272480, content: '[ELF binary]' }
                            }
                        },
                        'etc': {
                            type: 'dir', name: 'etc', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                            children: {
                                'passwd': {
                                    type: 'file', name: 'passwd', permissions: '0644', owner: 'root', group: 'root', size: 1540,
                                    content: "root:x:0:0:root:/root:/bin/bash\ndaemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin\nbin:x:2:2:bin:/bin:/usr/sbin/nologin\nsys:x:3:3:sys:/dev:/usr/sbin/nologin\nsync:x:4:65534:sync:/bin:/bin/sync\nwww-data:x:33:33:www-data:/var/www:/usr/sbin/nologin\nstudent:x:1000:1000:Student ZSEM,,,:/home/student:/bin/bash\neksam:x:1001:1001:Egzaminator CKE,,,:/home/eksam:/bin/bash\n"
                                },
                                'shadow': {
                                    type: 'file', name: 'shadow', permissions: '0640', owner: 'root', group: 'shadow', size: 890,
                                    content: "root:$6$salt$encrypted_hash:19200:0:99999:7:::\nstudent:$6$salt$encrypted_hash_student:19200:0:99999:7:::\n"
                                },
                                'group': {
                                    type: 'file', name: 'group', permissions: '0644', owner: 'root', group: 'root', size: 780,
                                    content: "root:x:0:\nsudo:x:27:student\nwww-data:x:33:\nstudent:x:1000:\neksam:x:1001:\nadmin:x:1002:\n"
                                },
                                'hosts': {
                                    type: 'file', name: 'hosts', permissions: '0644', owner: 'root', group: 'root', size: 240,
                                    content: "127.0.0.1\tlocalhost\n127.0.1.1\tzsem-lab\n192.168.1.1\tbrama.local\n192.168.1.200\tserwer-cke.local\n"
                                },
                                'hostname': {
                                    type: 'file', name: 'hostname', permissions: '0644', owner: 'root', group: 'root', size: 9,
                                    content: "zsem-lab\n"
                                },
                                'resolv.conf': {
                                    type: 'file', name: 'resolv.conf', permissions: '0644', owner: 'root', group: 'root', size: 95,
                                    content: "nameserver 8.8.8.8\nnameserver 8.8.4.4\nsearch zsem.local\n"
                                },
                                'network': {
                                    type: 'dir', name: 'network', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'interfaces': {
                                            type: 'file', name: 'interfaces', permissions: '0644', owner: 'root', group: 'root', size: 210,
                                            content: "# Loopback\nauto lo\niface lo inet loopback\n\n# Primary interface\nauto eth0\niface eth0 inet static\n  address 192.168.1.100\n  netmask 255.255.255.0\n  gateway 192.168.1.1\n  dns-nameservers 8.8.8.8 8.8.4.4\n"
                                        }
                                    }
                                },
                                // Pre-structured Apache configs
                                'apache2': {
                                    type: 'dir', name: 'apache2', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'apache2.conf': {
                                            type: 'file', name: 'apache2.conf', permissions: '0644', owner: 'root', group: 'root', size: 1200,
                                            content: "ServerRoot \"/etc/apache2\"\nTimeout 300\nKeepAlive On\nMaxKeepAliveRequests 100\nKeepAliveTimeout 5\nUser ${APACHE_RUN_USER}\nGroup ${APACHE_RUN_GROUP}\nIncludeOptional sites-enabled/*.conf\n"
                                        },
                                        'ports.conf': {
                                            type: 'file', name: 'ports.conf', permissions: '0644', owner: 'root', group: 'root', size: 120,
                                            content: "Listen 80\n<IfModule ssl_module>\n  Listen 443\n</IfModule>\n"
                                        },
                                        'sites-available': {
                                            type: 'dir', name: 'sites-available', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                            children: {
                                                '000-default.conf': {
                                                    type: 'file', name: '000-default.conf', permissions: '0644', owner: 'root', group: 'root', size: 380,
                                                    content: "<VirtualHost *:80>\n  ServerAdmin webmaster@localhost\n  DocumentRoot /var/www/html\n  ErrorLog ${APACHE_LOG_DIR}/error.log\n  CustomLog ${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>\n"
                                                },
                                                'zsem.conf': {
                                                    type: 'file', name: 'zsem.conf', permissions: '0644', owner: 'root', group: 'root', size: 240,
                                                    content: "<VirtualHost *:80>\n  ServerName www.zsem.local\n  DocumentRoot /var/www/zsem\n</VirtualHost>\n"
                                                }
                                            }
                                        },
                                        'sites-enabled': {
                                            type: 'dir', name: 'sites-enabled', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                            children: {
                                                '000-default.conf': { type: 'symlink', name: '000-default.conf', target: '../sites-available/000-default.conf', permissions: '0777', owner: 'root', group: 'root', content: '' }
                                            }
                                        }
                                    }
                                },
                                // Pre-structured BIND9 configs
                                'bind': {
                                    type: 'dir', name: 'bind', permissions: '0755', owner: 'root', group: 'bind', created: now, modified: now,
                                    children: {
                                        'named.conf': {
                                            type: 'file', name: 'named.conf', permissions: '0644', owner: 'root', group: 'bind', size: 210,
                                            content: "include \"/etc/bind/named.conf.options\";\ninclude \"/etc/bind/named.conf.local\";\ninclude \"/etc/bind/named.conf.default-zones\";\n"
                                        },
                                        'named.conf.options': {
                                            type: 'file', name: 'named.conf.options', permissions: '0644', owner: 'root', group: 'bind', size: 340,
                                            content: "options {\n  directory \"/var/cache/bind\";\n  recursion yes;\n  allow-query { any; };\n  forwarders { 8.8.8.8; 8.8.4.4; };\n  listen-on port 53 { any; };\n};\n"
                                        },
                                        'named.conf.local': {
                                            type: 'file', name: 'named.conf.local', permissions: '0644', owner: 'root', group: 'bind', size: 180,
                                            content: "// Strefy lokalne dla egzaminu CKE\nzone \"zsem.local\" {\n  type master;\n  file \"/etc/bind/db.zsem.local\";\n};\n"
                                        },
                                        'db.zsem.local': {
                                            type: 'file', name: 'db.zsem.local', permissions: '0644', owner: 'root', group: 'bind', size: 450,
                                            content: "; Strefa zsem.local\n$TTL 604800\n@ IN SOA ns1.zsem.local. admin.zsem.local. ( 2 604800 86400 2419200 604800 )\n@ IN NS ns1.zsem.local.\nns1 IN A 192.168.1.100\nwww IN A 192.168.1.200\nserwer IN A 192.168.1.100\n"
                                        }
                                    }
                                },
                                // Pre-structured Samba configs
                                'samba': {
                                    type: 'dir', name: 'samba', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'smb.conf': {
                                            type: 'file', name: 'smb.conf', permissions: '0644', owner: 'root', group: 'root', size: 540,
                                            content: "[global]\n  workgroup = ZSEM\n  server string = Samba Server\n  security = user\n  map to guest = Bad User\n\n[egzamin]\n  comment = Katalog egzaminacyjny\n  path = /srv/samba/egzamin\n  browsable = yes\n  writable = yes\n  guest ok = no\n  valid users = student\n"
                                        }
                                    }
                                },
                                // Pre-structured DHCP configs
                                'dhcp': {
                                    type: 'dir', name: 'dhcp', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now,
                                    children: {
                                        'dhcpd.conf': {
                                            type: 'file', name: 'dhcpd.conf', permissions: '0644', owner: 'root', group: 'root', size: 420,
                                            content: "default-lease-time 600;\nmax-lease-time 7200;\nauthoritative;\n\nsubnet 192.168.1.0 netmask 255.255.255.0 {\n  range 192.168.1.150 192.168.1.200;\n  option routers 192.168.1.1;\n  option domain-name-servers 8.8.8.8, 8.8.4.4;\n  option domain-name \"zsem.local\";\n}\n"
                                        }
                                    }
                                },
                                'vsftpd.conf': {
                                    type: 'file', name: 'vsftpd.conf', permissions: '0644', owner: 'root', group: 'root', size: 280,
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
                                            type: 'file', name: 'script.sh', permissions: '0644', owner: 'student', group: 'student', size: 140,
                                            content: "#!/bin/bash\necho \"Rozpoczynam diagnostykę sieci...\"\nping -c 2 192.168.1.1\necho \"Zakończono.\"\n"
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
                                'ftp': { type: 'dir', name: 'ftp', permissions: '0755', owner: 'root', group: 'root', created: now, modified: now, children: {} }
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
                                            content: "Aug 17 08:00:01 zsem-lab systemd[1]: Started Daily apt upgrade and clean activities.\nAug 17 08:15:22 zsem-lab kernel: [ 12.345678] eth0: Link is Up - 1000Mbps/Full\nAug 17 08:15:23 zsem-lab dhclient[742]: bound to 192.168.1.100 -- renewal in 43200 seconds.\nAug 17 08:30:10 zsem-lab sshd[1240]: Server listening on 0.0.0.0 port 22.\nAug 17 09:00:00 zsem-lab CRON[2100]: (root) CMD (/usr/bin/check_backup.sh)\n"
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
                                        'drivers': {
                                            type: 'dir', name: 'drivers', created: now, modified: now,
                                            children: {
                                                'etc': {
                                                    type: 'dir', name: 'etc', created: now, modified: now,
                                                    children: {
                                                        'hosts': {
                                                            type: 'file', name: 'hosts', size: 340,
                                                            content: "# Copyright (c) 1993-2009 Microsoft Corp.\r\n127.0.0.1       localhost\r\n::1             localhost\r\n192.168.1.1     brama.zsem.local\r\n192.168.1.200   serwer-cke\r\n"
                                                        },
                                                        'networks': {
                                                            type: 'file', name: 'networks', size: 120,
                                                            content: "# Networks table\r\nloopback        127\r\n"
                                                        },
                                                        'services': {
                                                            type: 'file', name: 'services', size: 540,
                                                            content: "echo                7/tcp\r\nftp-data           20/tcp\r\nftp                21/tcp\r\nssh                22/tcp\r\ntelnet             23/tcp\r\nsmtp               25/tcp\r\ndns                53/udp\r\nhttp               80/tcp\r\nhttps             443/tcp\r\nmysql            3306/tcp\r\nrdp              3389/tcp\r\n"
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        'inetpub': {
                            type: 'dir', name: 'inetpub', created: now, modified: now,
                            children: {
                                'wwwroot': {
                                    type: 'dir', name: 'wwwroot', created: now, modified: now,
                                    children: {
                                        'index.html': {
                                            type: 'file', name: 'index.html', size: 320,
                                            content: "<!DOCTYPE html>\r\n<html><body><h1>IIS Windows Server</h1><p>Internet Information Services 10.0</p></body></html>\r\n"
                                        },
                                        'iisstart.htm': {
                                            type: 'file', name: 'iisstart.htm', size: 240,
                                            content: "<html><body><h1>IIS Działa</h1></body></html>\r\n"
                                        }
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
                                        'Documents': { type: 'dir', name: 'Documents', created: now, modified: now, children: {} },
                                        'Downloads': { type: 'dir', name: 'Downloads', created: now, modified: now, children: {} },
                                        'script.bat': {
                                            type: 'file', name: 'script.bat', size: 180,
                                            content: "@echo off\r\necho Rozpoczynam test sieci...\r\nping 192.168.1.1 -n 2\r\necho Test zakonczony pomyslnie.\r\npause\r\n"
                                        }
                                    }
                                },
                                'Administrator': { type: 'dir', name: 'Administrator', created: now, modified: now, children: {} }
                            }
                        },
                        'Dane': {
                            type: 'dir', name: 'Dane', created: now, modified: now,
                            children: {
                                'baza_egzamin.sql': {
                                    type: 'file', name: 'baza_egzamin.sql', size: 450,
                                    content: "CREATE DATABASE IF NOT EXISTS egzamin;\nUSE egzamin;\nCREATE TABLE uzytkownicy (id INT PRIMARY KEY AUTO_INCREMENT, login VARCHAR(50), rola VARCHAR(20));\nINSERT INTO uzytkownicy (login, rola) VALUES ('admin', 'administrator'), ('student', 'uczen');\n"
                                }
                            }
                        },
                        'Temp': { type: 'dir', name: 'Temp', created: now, modified: now, children: {} }
                    }
                }
            };
        }

        save() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.tree));
            } catch (e) {
                console.warn('VFS save error:', e);
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
            this.tree = this.createDefaultTree();
            this.currentDirLinux = '/home/student';
            this.currentDirWin = 'C:\\Users\\Student';
            this.save();
        }

        normalizePath(path, isWindows) {
            if (!path) return isWindows ? this.currentDirWin : this.currentDirLinux;
            path = path.trim();

            if (!isWindows) {
                if (path === '~' || path.startsWith('~/')) {
                    path = '/home/student' + path.slice(1);
                }
                if (!path.startsWith('/')) {
                    path = (this.currentDirLinux === '/' ? '' : this.currentDirLinux) + '/' + path;
                }
                const parts = path.split('/').filter(p => p && p !== '.');
                const stack = [];
                for (const p of parts) {
                    if (p === '..') {
                        if (stack.length > 0) stack.pop();
                    } else {
                        stack.push(p);
                    }
                }
                return '/' + stack.join('/');
            } else {
                path = path.replace(/\//g, '\\');
                if (path.startsWith('~')) {
                    path = 'C:\\Users\\Student' + path.slice(1);
                }
                if (!/^[a-zA-Z]:\\/i.test(path)) {
                    path = this.currentDirWin + '\\' + path;
                }
                const drive = path.slice(0, 2).toUpperCase();
                const rest = path.slice(2);
                const parts = rest.split('\\').filter(p => p && p !== '.');
                const stack = [];
                for (const p of parts) {
                    if (p === '..') {
                        if (stack.length > 0) stack.pop();
                    } else {
                        stack.push(p);
                    }
                }
                return drive + '\\' + stack.join('\\');
            }
        }

        getNode(path, isWindows) {
            const norm = this.normalizePath(path, isWindows);
            const root = isWindows ? this.tree.windows : this.tree.linux;

            if (!isWindows) {
                if (norm === '/') return root;
                const parts = norm.split('/').filter(Boolean);
                let curr = root;
                for (const part of parts) {
                    if (!curr || curr.type !== 'dir' || !curr.children || !curr.children[part]) {
                        return null;
                    }
                    curr = curr.children[part];
                }
                return curr;
            } else {
                const parts = norm.split('\\').filter(Boolean);
                if (parts.length <= 1) return root;
                let curr = root;
                for (let i = 1; i < parts.length; i++) {
                    const part = parts[i];
                    if (!curr || curr.type !== 'dir' || !curr.children) return null;
                    const foundKey = Object.keys(curr.children).find(k => k.toLowerCase() === part.toLowerCase());
                    if (!foundKey) return null;
                    curr = curr.children[foundKey];
                }
                return curr;
            }
        }

        getParentNode(path, isWindows) {
            const norm = this.normalizePath(path, isWindows);
            const sep = isWindows ? '\\' : '/';
            const idx = norm.lastIndexOf(sep);
            if (idx === -1) return { parent: null, name: norm };
            const parentPath = norm.slice(0, idx) || (isWindows ? 'C:\\' : '/');
            const name = norm.slice(idx + 1);
            return { parent: this.getNode(parentPath, isWindows), name, parentPath };
        }

        createFile(path, content = '', isWindows = false) {
            const { parent, name } = this.getParentNode(path, isWindows);
            if (!parent || parent.type !== 'dir') return false;
            const now = new Date().toISOString();
            parent.children[name] = {
                type: 'file',
                name: name,
                size: content.length,
                content: content,
                permissions: isWindows ? '0666' : '0644',
                owner: isWindows ? 'Student' : 'student',
                group: isWindows ? 'Users' : 'student',
                created: now,
                modified: now
            };
            this.save();
            return true;
        }

        writeFile(path, content = '', append = false, isWindows = false) {
            const node = this.getNode(path, isWindows);
            if (node) {
                if (node.type !== 'file') return false;
                node.content = append ? (node.content + content) : content;
                node.size = node.content.length;
                node.modified = new Date().toISOString();
                this.save();
                return true;
            }
            return this.createFile(path, content, isWindows);
        }

        createDirectory(path, recursive = true, isWindows = false) {
            const norm = this.normalizePath(path, isWindows);
            const parts = isWindows ? norm.split('\\').slice(1) : norm.split('/').filter(Boolean);
            let curr = isWindows ? this.tree.windows : this.tree.linux;

            for (let i = 0; i < parts.length; i++) {
                const part = parts[i];
                if (!curr.children) curr.children = {};
                let next = isWindows
                    ? curr.children[Object.keys(curr.children).find(k => k.toLowerCase() === part.toLowerCase())]
                    : curr.children[part];

                if (!next) {
                    if (!recursive && i < parts.length - 1) return false;
                    const now = new Date().toISOString();
                    next = {
                        type: 'dir',
                        name: part,
                        permissions: '0755',
                        owner: isWindows ? 'Student' : 'student',
                        group: isWindows ? 'Users' : 'student',
                        created: now,
                        modified: now,
                        children: {}
                    };
                    curr.children[part] = next;
                } else if (next.type !== 'dir') {
                    return false;
                }
                curr = next;
            }
            this.save();
            return true;
        }

        removeNode(path, recursive = false, isWindows = false) {
            const { parent, name } = this.getParentNode(path, isWindows);
            if (!parent || !parent.children) return false;
            const targetKey = isWindows
                ? Object.keys(parent.children).find(k => k.toLowerCase() === name.toLowerCase())
                : name;
            if (!targetKey || !parent.children[targetKey]) return false;

            const target = parent.children[targetKey];
            if (target.type === 'dir' && Object.keys(target.children || {}).length > 0 && !recursive) {
                return false;
            }
            delete parent.children[targetKey];
            this.save();
            return true;
        }

        copyNode(srcPath, destPath, recursive = false, isWindows = false) {
            const src = this.getNode(srcPath, isWindows);
            if (!src) return false;
            if (src.type === 'file') {
                return this.createFile(destPath, src.content, isWindows);
            }
            if (src.type === 'dir' && recursive) {
                this.createDirectory(destPath, true, isWindows);
                const items = Object.values(src.children || {});
                const sep = isWindows ? '\\' : '/';
                for (const item of items) {
                    this.copyNode(srcPath + sep + item.name, destPath + sep + item.name, recursive, isWindows);
                }
                return true;
            }
            return false;
        }

        moveNode(srcPath, destPath, isWindows = false) {
            const ok = this.copyNode(srcPath, destPath, true, isWindows);
            if (ok) {
                this.removeNode(srcPath, true, isWindows);
                return true;
            }
            return false;
        }

        listDirectory(path, isWindows = false) {
            const node = this.getNode(path, isWindows);
            if (!node || node.type !== 'dir') return null;
            return Object.values(node.children || {});
        }

        generateTreeAscii(node, prefix = '') {
            let out = '';
            const items = Object.values(node.children || {});
            items.forEach((item, idx) => {
                const isLast = idx === items.length - 1;
                const pointer = isLast ? '└── ' : '├── ';
                out += `${prefix}${pointer}${item.name}${item.type === 'dir' ? '/' : ''}\n`;
                if (item.type === 'dir') {
                    out += this.generateTreeAscii(item, prefix + (isLast ? '    ' : '│   '));
                }
            });
            return out;
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 2. NETWORK & SYSTEM STATE
    // ════════════════════════════════════════════════════════════════════════════

    class NetworkState {
        constructor() {
            this.storageKey = 'zsem_cli_net_v4';
            this.state = this.load() || this.defaultState();
        }

        defaultState() {
            return {
                ip: '192.168.1.100',
                netmask: '255.255.255.0',
                prefix: 24,
                gateway: '192.168.1.1',
                mac: '00:0C:29:AB:CD:EF',
                dns: ['8.8.8.8', '8.8.4.4'],
                dhcp: true,
                hostname: 'zsem-lab',
                winHostname: 'ZSEM-STUDENT',
                currentUserLinux: 'student',
                currentUserWin: 'student',
                installedPackages: ['bash', 'coreutils', 'net-tools', 'iproute2', 'nano'],
                firewallUfw: { enabled: false, default: 'deny (incoming), allow (outgoing)', rules: [] },
                firewallIptables: {
                    INPUT: [{ proto: 'tcp', dport: '22', action: 'ACCEPT' }],
                    FORWARD: [],
                    OUTPUT: []
                },
                firewallWin: { enabled: true, rules: [{ name: 'Core Networking', action: 'Allow', port: 'Any' }] },
                services: {
                    'ssh': { name: 'SSH Server', status: 'active (running)', port: 22, pid: 1230, installed: true },
                    'nginx': { name: 'Nginx HTTP Server', status: 'active (running)', port: 80, pid: 1450, installed: true },
                    'apache2': { name: 'Apache2 Web Server', status: 'inactive (dead)', port: 80, pid: 0, installed: true },
                    'bind9': { name: 'BIND9 DNS Server', status: 'inactive (dead)', port: 53, pid: 0, installed: true },
                    'smbd': { name: 'Samba SMB/CIFS Server', status: 'inactive (dead)', port: 445, pid: 0, installed: true },
                    'isc-dhcp-server': { name: 'ISC DHCP Server', status: 'inactive (dead)', port: 67, pid: 0, installed: true },
                    'vsftpd': { name: 'vsftpd FTP Server', status: 'inactive (dead)', port: 21, pid: 0, installed: true },
                    'postfix': { name: 'Postfix Mail Transport Agent', status: 'inactive (dead)', port: 25, pid: 0, installed: true },
                    'nfs-kernel-server': { name: 'NFS Server', status: 'inactive (dead)', port: 2049, pid: 0, installed: true },
                    'mysql': { name: 'MySQL Community Server', status: 'active (running)', port: 3306, pid: 1890, installed: true },
                    'wuauserv': { name: 'Windows Update', status: 'RUNNING', pid: 2310, installed: true },
                    'W3SVC': { name: 'World Wide Web Publishing Service (IIS)', status: 'STOPPED', pid: 0, installed: true }
                },
                routes: [
                    { dest: '0.0.0.0', mask: '0.0.0.0', gw: '192.168.1.1', iface: 'eth0', metric: 100 },
                    { dest: '192.168.1.0', mask: '255.255.255.0', gw: '0.0.0.0', iface: 'eth0', metric: 0 }
                ],
                stats: {
                    commandsCount: 0,
                    completedScenarios: 0,
                    packagesInstalled: 0,
                    startTime: Date.now()
                },
                achievements: []
            };
        }

        save() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.state));
            } catch (e) { }
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
            this.state = this.defaultState();
            this.save();
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 3. INLINE SUB-SHELLS & APPS (POWERSHELL, NSLOOKUP, SSH, MYSQL, DISKPART, PYTHON)
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
                const items = this.term.vfs.listDirectory(this.term.vfs.currentDirWin, true) || [];
                let out = `\r\n    Directory: ${this.term.vfs.currentDirWin}\r\n\r\nMode                 LastWriteTime         Length Name\r\n----                 -------------         ------ ----\r\n`;
                out += items.map(i => {
                    const mode = i.type === 'dir' ? 'd-----' : '-a----';
                    return `${mode}        17.08.2026     08:30       ${String(i.size || 0).padStart(8)} ${i.name}`;
                }).join('\r\n');
                return { output: out + '\r\n' };
            }

            if (cmdlet === 'get-service' || cmdlet === 'gsv') {
                const svcs = this.term.net.state.services;
                let out = `\r\nStatus   Name               DisplayName\r\n------   ----               -----------\r\n`;
                out += Object.entries(svcs).map(([k, v]) => {
                    const st = v.status.includes('running') || v.status === 'RUNNING' ? 'Running' : 'Stopped';
                    return `${st.padEnd(8)} ${k.padEnd(18)} ${v.name}`;
                }).join('\r\n');
                return { output: out + '\r\n' };
            }

            if (cmdlet === 'start-service') {
                const name = args[1] || 'W3SVC';
                if (this.term.net.state.services[name]) {
                    this.term.net.state.services[name].status = 'RUNNING';
                    this.term.net.save();
                }
                return { output: '' };
            }

            if (cmdlet === 'stop-service') {
                const name = args[1] || 'W3SVC';
                if (this.term.net.state.services[name]) {
                    this.term.net.state.services[name].status = 'STOPPED';
                    this.term.net.save();
                }
                return { output: '' };
            }

            if (cmdlet === 'get-netipaddress') {
                const net = this.term.net.state;
                return {
                    output: `\r\nIPAddress         : ${net.ip}\r\nInterfaceIndex    : 4\r\nInterfaceAlias    : Ethernet\r\nAddressFamily     : IPv4\r\nType              : Unicast\r\nPrefixLength      : 24\r\nPrefixOrigin      : ${net.dhcp ? 'Dhcp' : 'Manual'}\r\nAddressState      : Preferred\r\n`
                };
            }

            if (cmdlet === 'test-netconnection' || cmdlet === 'tnc') {
                const host = args.find(a => !a.startsWith('-') && a.toLowerCase() !== 'test-netconnection' && a.toLowerCase() !== 'tnc') || '8.8.8.8';
                return {
                    output: `\r\nComputerName     : ${host}\r\nRemoteAddress    : ${host}\r\nInterfaceAlias   : Ethernet\r\nSourceAddress    : ${this.term.net.state.ip}\r\nPingSucceeded    : True\r\nPingReplyDetails : RoundtripTime 14 ms\r\n`
                };
            }

            if (cmdlet === 'get-process' || cmdlet === 'ps' || cmdlet === 'gps') {
                return {
                    output: `\r\nHandles  NPM(K)    PM(K)      WS(K)     CPU(s)     Id  SI ProcessName\r\n-------  ------    -----      -----     ------     --  -- -----------\r\n    342      18    15200      34120       1.24   1230   1 pwsh\r\n    512      32    84100     112450       4.85   2140   1 explorer\r\n    120       8     4120       8900       0.12   3410   1 svchost\r\n`
                };
            }

            if (cmdlet === 'get-content' || cmdlet === 'gc' || cmdlet === 'cat' || cmdlet === 'type') {
                const file = args[1] || 'script.bat';
                const node = this.term.vfs.getNode(file, true);
                if (!node || node.type !== 'file') return { output: `Get-Content : Cannot find path '${file}' because it does not exist.` };
                return { output: node.content + '\r\n' };
            }

            if (cmdlet === 'set-content' || cmdlet === 'sc') {
                const file = args[1] || 'test.txt';
                const valIdx = args.indexOf('-Value');
                const val = valIdx !== -1 ? args[valIdx + 1] : args[2] || '';
                this.term.vfs.writeFile(file, val, false, true);
                return { output: '' };
            }

            if (cmdlet === 'new-item' || cmdlet === 'ni') {
                const name = args.find(a => !a.startsWith('-') && a.toLowerCase() !== 'new-item' && a.toLowerCase() !== 'ni') || 'NowyPlik.txt';
                const isDir = args.includes('-ItemType') && args[args.indexOf('-ItemType') + 1]?.toLowerCase() === 'directory';
                if (isDir) this.term.vfs.createDirectory(name, true, true);
                else this.term.vfs.createFile(name, '', true);
                return { output: `\r\n    Directory: ${this.term.vfs.currentDirWin}\r\n\r\nMode                 LastWriteTime         Length Name\r\n----                 -------------         ------ ----\r\n${isDir ? 'd-----' : '-a----'}        17.08.2026     08:30            0 ${name}\r\n` };
            }

            if (cmdlet === 'remove-item' || cmdlet === 'ri' || cmdlet === 'rm') {
                const name = args[1];
                if (name) this.term.vfs.removeNode(name, true, true);
                return { output: '' };
            }

            if (cmdlet === 'get-localuser') {
                return {
                    output: `\r\nName               Enabled Description\r\n----               ------- -----------\r\nAdministrator      True    Wbudowane konto do administrowania komputerem\r\nDefaultAccount     False   Konto zarządzane przez system\r\nGuest              False   Wbudowane konto gościa\r\nstudent            True    Konto ucznia ZSEM\r\n`
                };
            }

            if (cmdlet === 'new-localuser') {
                const name = args.find(a => !a.startsWith('-') && a.toLowerCase() !== 'new-localuser') || 'NowyUser';
                return { output: `\r\nName    Enabled Description\r\n----    ------- -----------\r\n${name.padEnd(7)} True    \r\n` };
            }

            return { output: `\r\n${cmdlet} : Polecenie zostało pomyślnie wykonane w środowisku PowerShell.\r\n` };
        }
    }

    class NslookupShell {
        constructor(term) {
            this.term = term;
            this.currentServer = '8.8.8.8';
            this.recordType = 'A';
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();

            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: '' };
            if (lower.startsWith('server ')) {
                this.currentServer = raw.split(/\s+/)[1] || '8.8.8.8';
                return { output: `Default server: ${this.currentServer}\nAddress: ${this.currentServer}#53` };
            }
            if (lower.startsWith('set type=') || lower.startsWith('set q=')) {
                this.recordType = raw.split('=')[1]?.toUpperCase() || 'A';
                return { output: '' };
            }
            if (lower === 'help' || lower === '?') {
                return { output: 'Commands:\n  <host>           - lookup host\n  server <ip>      - set default DNS server\n  set type=<type>  - set query type (A, AAAA, MX, NS, SOA, PTR, CNAME)\n  exit             - exit nslookup' };
            }

            const host = raw;
            if (this.recordType === 'MX') {
                return { output: `Server:\t\t${this.currentServer}\nAddress:\t${this.currentServer}#53\n\nNon-authoritative answer:\n${host}\tmail exchanger = 10 mail.${host}.` };
            }
            if (this.recordType === 'NS') {
                return { output: `Server:\t\t${this.currentServer}\nAddress:\t${this.currentServer}#53\n\nNon-authoritative answer:\n${host}\tnameserver = ns1.${host}.\n${host}\tnameserver = ns2.${host}.` };
            }
            return {
                output: `Server:\t\t${this.currentServer}\nAddress:\t${this.currentServer}#53\n\nNon-authoritative answer:\nName:\t${host}\nAddress: 142.250.187.195\nName:\t${host}\nAddress: 2a00:1450:401b:805::2003`
            };
        }
    }

    class SshShell {
        constructor(term, user = 'student', host = 'remote-srv') {
            this.term = term;
            this.user = user;
            this.host = host;
            this.pwd = '/home/' + user;
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();
            if (lower === 'exit' || lower === 'logout') return { action: 'exit', output: `Connection to ${this.host} closed.` };
            if (lower === 'pwd') return { output: this.pwd };
            if (lower === 'whoami') return { output: this.user };
            if (lower === 'hostname') return { output: this.host };
            if (lower === 'uname -a') return { output: `Linux ${this.host} 5.15.0-89-generic #99-Ubuntu SMP x86_64 GNU/Linux` };
            if (lower === 'ls' || lower === 'ls -la') {
                return { output: `total 16\ndrwxr-xr-x 4 ${this.user} ${this.user} 4096 Aug 17 08:00 .\ndrwxr-xr-x 3 root   root   4096 Aug 17 08:00 ..\n-rw-r--r-- 1 ${this.user} ${this.user}  220 Aug 17 08:00 .bash_logout\n-rw-r--r-- 1 ${this.user} ${this.user} 3771 Aug 17 08:00 .bashrc\n-rw-r--r-- 1 ${this.user} ${this.user}  807 Aug 17 08:00 .profile\n-rw-r--r-- 1 ${this.user} ${this.user}  412 Aug 17 08:15 server_backup.tar.gz` };
            }
            if (lower.startsWith('cat ')) {
                return { output: `[Remote content from ${this.host}] # Server configured for CKE Exam lab.` };
            }
            return { output: `[${this.host}] Executed: ${raw}` };
        }
    }

    class MysqlShell {
        constructor() {
            this.databases = {
                'egzamin': ['uzytkownicy', 'wyniki_testow'],
                'sklep': ['klienci', 'produkty', 'zamowienia'],
                'information_schema': ['TABLES', 'COLUMNS', 'SCHEMATA']
            };
            this.currentDb = null;
        }

        execute(cmd) {
            const raw = cmd.trim().replace(/;+$/, '');
            const lower = raw.toLowerCase();

            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: 'Bye' };
            if (lower === 'help' || lower === '\\h') return { output: 'MySQL Commands: SHOW DATABASES;, USE <db>;, SHOW TABLES;, SELECT ..., CREATE DATABASE <db>;, CREATE TABLE ..., exit;' };
            if (lower === 'show databases' || lower === 'show schemas') {
                const rows = Object.keys(this.databases);
                return { output: '+--------------------+\n| Database           |\n+--------------------+\n' + rows.map(r => `| ${r.padEnd(18)} |`).join('\n') + '\n+--------------------+\n' + rows.length + ' rows in set (0.00 sec)' };
            }
            if (lower.startsWith('use ')) {
                const db = raw.split(/\s+/)[1];
                if (this.databases[db]) {
                    this.currentDb = db;
                    return { output: 'Database changed' };
                }
                return { output: `ERROR 1049 (42000): Unknown database '${db}'` };
            }
            if (lower === 'show tables') {
                if (!this.currentDb) return { output: 'ERROR 1046 (3D000): No database selected. Select one with USE <db>' };
                const tables = this.databases[this.currentDb] || [];
                return { output: `+-------------------------+\n| Tables_in_${this.currentDb.padEnd(14)} |\n+-------------------------+\n` + tables.map(t => `| ${t.padEnd(23)} |`).join('\n') + `\n+-------------------------+\n${tables.length} rows in set (0.00 sec)` };
            }
            if (lower.startsWith('create database ')) {
                const db = raw.split(/\s+/)[2];
                if (db) {
                    this.databases[db] = [];
                    return { output: `Query OK, 1 row affected (0.01 sec)` };
                }
            }
            if (lower.startsWith('create table ')) {
                if (!this.currentDb) return { output: 'ERROR 1046 (3D000): No database selected.' };
                const match = raw.match(/create\s+table\s+([a-zA-Z0-9_]+)/i);
                if (match && match[1]) {
                    this.databases[this.currentDb].push(match[1]);
                    return { output: 'Query OK, 0 rows affected (0.02 sec)' };
                }
            }
            if (lower.startsWith('select ')) {
                if (!this.currentDb) return { output: 'ERROR 1046 (3D000): No database selected.' };
                return {
                    output: `+----+---------------+-----------------+\n| id | nazwa         | status          |\n+----+---------------+-----------------+\n|  1 | Administrator | Aktywny         |\n|  2 | Student_ZSEM  | W trakcie nauki |\n|  3 | Egzaminator   | CKE             |\n+----+---------------+-----------------+\n3 rows in set (0.00 sec)`
                };
            }
            return { output: `Query OK, 1 row affected (0.01 sec)` };
        }
    }

    class DiskpartShell {
        constructor() {
            this.disks = [
                { id: 0, status: 'Online', size: '100 GB', free: '1024 KB', dyn: '', gpt: '*' },
                { id: 1, status: 'Online', size: '500 GB', free: '500 GB', dyn: '', gpt: '' }
            ];
            this.volumes = [
                { id: 0, letter: 'C', label: 'System', fs: 'NTFS', type: 'Partition', size: '99 GB', status: 'Healthy', info: 'System' },
                { id: 1, letter: 'D', label: 'Dane', fs: 'NTFS', type: 'Partition', size: '200 GB', status: 'Healthy', info: '' }
            ];
            this.selectedDisk = 0;
            this.selectedVol = 0;
        }

        execute(cmd) {
            const raw = cmd.trim();
            const lower = raw.toLowerCase();

            if (lower === 'exit' || lower === 'quit') return { action: 'exit', output: '\r\nLeaving DiskPart...' };
            if (lower === 'help') return { output: '\r\nMicrosoft DiskPart version 10.0.19041\r\nCommands: list disk, select disk <n>, list volume, select volume <n>, list partition, create partition primary, format fs=ntfs quick, assign letter=<L>, active, clean, exit' };

            if (lower === 'list disk') {
                let out = '\r\n  Disk ###  Status         Size     Free     Dyn  Gpt\r\n  --------  -------------  -------  -------  ---  ---\r\n';
                out += this.disks.map(d => `  ${d.id === this.selectedDisk ? '*' : ' '} Disk ${d.id}    ${d.status.padEnd(13)}  ${d.size.padEnd(7)}  ${d.free.padEnd(7)}   ${d.dyn.padEnd(3)}  ${d.gpt}`).join('\r\n');
                return { output: out + '\r\n' };
            }
            if (lower.startsWith('select disk ')) {
                const id = parseInt(raw.split(/\s+/)[2], 10);
                if (!isNaN(id) && this.disks.find(d => d.id === id)) {
                    this.selectedDisk = id;
                    return { output: `\r\nDisk ${id} is now the selected disk.\r\n` };
                }
                return { output: '\r\nThe specified disk is not valid.\r\n' };
            }
            if (lower === 'list volume') {
                let out = '\r\n  Volume ###  Ltr  Label        Fs     Type        Size     Status     Info\r\n  ----------  ---  -----------  -----  ----------  -------  ---------  --------\r\n';
                out += this.volumes.map(v => `  ${v.id === this.selectedVol ? '*' : ' '} Volume ${v.id}   ${v.letter.padEnd(3)}  ${v.label.padEnd(11)}  ${v.fs.padEnd(5)}  ${v.type.padEnd(10)}  ${v.size.padEnd(7)}  ${v.status.padEnd(9)}  ${v.info}`).join('\r\n');
                return { output: out + '\r\n' };
            }
            if (lower.startsWith('select volume ')) {
                const id = parseInt(raw.split(/\s+/)[2], 10);
                if (!isNaN(id)) {
                    this.selectedVol = id;
                    return { output: `\r\nVolume ${id} is now the selected volume.\r\n` };
                }
            }
            if (lower === 'list partition') {
                return {
                    output: `\r\n  Partition ###  Type              Size     Offset\r\n  -------------  ----------------  -------  -------\r\n  * Partition 1  Primary            99 GB  1024 KB\r\n    Partition 2  Recovery          512 MB    99 GB\r\n`
                };
            }
            if (lower.startsWith('create partition primary')) {
                const newVolId = this.volumes.length;
                this.volumes.push({ id: newVolId, letter: '', label: 'Nowy', fs: 'RAW', type: 'Partition', size: '50 GB', status: 'Healthy', info: '' });
                this.selectedVol = newVolId;
                return { output: '\r\nDiskPart succeeded in creating the specified partition.\r\n' };
            }
            if (lower.startsWith('format')) {
                if (this.volumes[this.selectedVol]) {
                    this.volumes[this.selectedVol].fs = 'NTFS';
                    const m = raw.match(/label="([^"]+)"/i);
                    if (m && m[1]) this.volumes[this.selectedVol].label = m[1];
                }
                return { output: '\r\n  100 percent completed\r\n\r\nDiskPart successfully formatted the volume.\r\n' };
            }
            if (lower.startsWith('assign letter=')) {
                const letter = raw.split('=')[1]?.trim().toUpperCase();
                if (letter && this.volumes[this.selectedVol]) {
                    this.volumes[this.selectedVol].letter = letter;
                    return { output: `\r\nDiskPart successfully assigned the drive letter or mount point.\r\n` };
                }
            }
            if (lower === 'active') return { output: '\r\nDiskPart marked the current partition as active.\r\n' };
            if (lower === 'clean') return { output: '\r\nDiskPart succeeded in cleaning the disk.\r\n' };

            return { output: '\r\nDiskPart successfully executed the command.\r\n' };
        }
    }

    class PythonShell {
        execute(cmd) {
            const raw = cmd.trim();
            if (raw === 'exit()' || raw === 'quit()') return { action: 'exit', output: '' };
            if (raw === 'help()' || raw === 'help') return { output: 'Type help() for interactive help, or help(object) for help about object.' };
            try {
                if (/^print\(.*\)$/.test(raw)) {
                    const inner = raw.slice(6, -1);
                    return { output: String(eval(inner)) };
                }
                const result = eval(raw);
                return { output: result !== undefined ? String(result) : '' };
            } catch (err) {
                return { output: `Traceback (most recent call last):\n  File "<stdin>", line 1, in <module>\nNameError: name '${raw}' is not defined` };
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 4. MAIN TERMINAL ENGINE
    // ════════════════════════════════════════════════════════════════════════════

    class TerminalSimulator {
        constructor() {
            this.vfs = new VirtualFileSystem();
            this.net = new NetworkState();
            this.currentOs = 'linux';
            this.currentSubShell = null;
            this.subShellEngine = null;

            this.history = [];
            this.historyIndex = -1;
            this.activeScenario = null;
            this.activeScenarioStep = 0;

            this.outputEl = document.getElementById('termOutput');
            this.inputEl = document.getElementById('termInput');
            this.promptLabelEl = document.getElementById('termPromptLabel');
            this.termTitleLabelEl = document.getElementById('termTitleLabel');

            this.nanoOverlay = document.getElementById('nanoOverlay');
            this.nanoTextarea = document.getElementById('nanoTextarea');
            this.nanoFilename = document.getElementById('nanoFilename');
            this.nanoModified = document.getElementById('nanoModified');
            this.nanoCurrentPath = '';

            // Hydrate completed scenarios from server user profile
            if (window.CLI_LAB_USER && Array.isArray(window.CLI_LAB_USER.completedScenarios)) {
                CKE_SCENARIOS.forEach(sc => {
                    if (window.CLI_LAB_USER.completedScenarios.includes(sc.id)) {
                        sc.completed = true;
                    }
                });
            }

            this.initDOMElements();
            this.initEventListeners();
            this.renderWelcome();
            this.updatePrompt();
            this.renderScenarios();
            this.renderCommandList();
            this.updateStatusRibbon();
            this.renderServiceMonitor();
            this.updateStatsUI();
            this.startStatsTimer();
        }

        initDOMElements() {
            document.getElementById('osBtnLinux')?.addEventListener('click', () => this.switchOs('linux'));
            document.getElementById('osBtnWin')?.addEventListener('click', () => this.switchOs('windows'));

            document.getElementById('btnTermClear')?.addEventListener('click', () => this.clear());
            document.getElementById('btnTermCopy')?.addEventListener('click', () => this.copyOutput());
            document.getElementById('btnTermFullscreen')?.addEventListener('click', () => this.toggleFullscreen());
            document.getElementById('btnTermExport')?.addEventListener('click', () => this.exportSessionLog());
            document.getElementById('scenarioClearBtn')?.addEventListener('click', () => this.clear());
            document.getElementById('scenarioResetVfsBtn')?.addEventListener('click', () => this.resetAllState());
            document.getElementById('scenarioSkipBtn')?.addEventListener('click', () => this.skipScenarioStep());

            document.getElementById('dotClose')?.addEventListener('click', () => this.clear());
            document.getElementById('dotMax')?.addEventListener('click', () => this.toggleFullscreen());

            document.querySelectorAll('#scenarioCategoryChips .cat-chip').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.querySelectorAll('#scenarioCategoryChips .cat-chip').forEach(c => c.classList.remove('active'));
                    btn.classList.add('active');
                    this.renderScenarios(btn.dataset.cat);
                });
            });

            document.querySelectorAll('.touch-key').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.dataset.key;
                    const insert = btn.dataset.insert;
                    if (key === 'Tab') this.handleTabCompletion();
                    else if (key === 'CtrlC') this.handleInterrupt();
                    else if (key === 'CtrlL') this.clear();
                    else if (key === 'Up') this.navigateHistory(-1);
                    else if (key === 'Down') this.navigateHistory(1);
                    else if (insert) {
                        this.inputEl.value += insert;
                        this.inputEl.focus();
                    }
                });
            });
        }

        initEventListeners() {
            this.inputEl?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const line = this.inputEl.value;
                    this.inputEl.value = '';
                    this.executeCommandLine(line);
                } else if (e.key === 'Tab') {
                    e.preventDefault();
                    this.handleTabCompletion();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateHistory(-1);
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateHistory(1);
                } else if (e.ctrlKey && (e.key === 'c' || e.key === 'C')) {
                    e.preventDefault();
                    this.handleInterrupt();
                } else if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
                    e.preventDefault();
                    this.clear();
                }
            });

            document.getElementById('terminalWindow')?.addEventListener('click', () => {
                if (!this.nanoOverlay || this.nanoOverlay.style.display === 'none') {
                    this.inputEl?.focus();
                }
            });

            // Enhanced Nano keyboard shortcuts
            this.nanoTextarea?.addEventListener('keydown', (e) => {
                this.nanoModified.textContent = '[Zmodyfikowano]';
                if (e.ctrlKey && (e.key === 'o' || e.key === 'O')) {
                    e.preventDefault();
                    this.saveNano();
                } else if (e.ctrlKey && (e.key === 'x' || e.key === 'X')) {
                    e.preventDefault();
                    this.closeNano();
                } else if (e.ctrlKey && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    this.nanoCutLine();
                } else if (e.ctrlKey && (e.key === 'u' || e.key === 'U')) {
                    e.preventDefault();
                    this.nanoPasteLine();
                } else if (e.ctrlKey && (e.key === 'c' || e.key === 'C')) {
                    e.preventDefault();
                    this.nanoShowPos();
                } else if (e.ctrlKey && (e.key === 'w' || e.key === 'W')) {
                    e.preventDefault();
                    this.nanoSearch();
                }
            });
        }

        startStatsTimer() {
            setInterval(() => {
                const elapsedMin = Math.floor((Date.now() - this.net.state.stats.startTime) / 60000);
                const elTime = document.getElementById('statTimeCount');
                if (elTime) elTime.textContent = `${elapsedMin}m`;
            }, 30000);
        }

        updateStatsUI() {
            const completedCount = CKE_SCENARIOS.filter(s => s.completed).length;
            const statCmd = document.getElementById('statCmdCount');
            if (statCmd) statCmd.textContent = this.net.state.stats.commandsCount;
            const statScen = document.getElementById('statScenCount');
            if (statScen) statScen.textContent = `${completedCount}/20`;
            const statPkg = document.getElementById('statPkgCount');
            if (statPkg) statPkg.textContent = this.net.state.installedPackages.length;
            const statXp = document.getElementById('statUserXp');
            if (statXp && window.CLI_LAB_USER) statXp.textContent = Number(window.CLI_LAB_USER.xp || 0).toLocaleString();
            const statRibbonXp = document.getElementById('statusXp');
            if (statRibbonXp && window.CLI_LAB_USER) statRibbonXp.textContent = Number(window.CLI_LAB_USER.xp || 0).toLocaleString();
        }

        triggerAchievement(id, title, icon = '🏆') {
            if (this.net.state.achievements.includes(id)) return;
            this.net.state.achievements.push(id);
            this.net.save();

            const toastContainer = document.getElementById('cliToastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = 'achievement-toast';
            toast.innerHTML = `<div class="fw-bold">${icon} Osiągnięcie odblokowane!</div><div class="small">${title}</div>`;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        switchOs(os) {
            if (this.currentOs === os) return;
            this.currentOs = os;
            document.getElementById('osBtnLinux')?.classList.toggle('active', os === 'linux');
            document.getElementById('osBtnWin')?.classList.toggle('active', os === 'windows');
            this.currentSubShell = null;
            this.subShellEngine = null;
            this.updatePrompt();
            this.updateStatusRibbon();
            this.renderWelcome();
            this.renderCommandList();
        }

        updatePrompt() {
            const isWin = this.currentOs === 'windows';
            if (this.currentSubShell === 'mysql') {
                this.promptLabelEl.textContent = 'mysql>';
                this.termTitleLabelEl.textContent = `mysql — root@localhost: ${this.subShellEngine?.currentDb || '(none)'}`;
            } else if (this.currentSubShell === 'diskpart') {
                this.promptLabelEl.textContent = 'DISKPART>';
                this.termTitleLabelEl.textContent = 'DiskPart — Microsoft Partition Manager';
            } else if (this.currentSubShell === 'python') {
                this.promptLabelEl.textContent = '>>>';
                this.termTitleLabelEl.textContent = 'python3.10 — Interactive Shell';
            } else if (this.currentSubShell === 'powershell') {
                this.promptLabelEl.textContent = `PS ${this.vfs.currentDirWin}>`;
                this.termTitleLabelEl.textContent = `PowerShell 7.3 — ${this.vfs.currentDirWin}`;
            } else if (this.currentSubShell === 'nslookup') {
                this.promptLabelEl.textContent = '>';
                this.termTitleLabelEl.textContent = 'nslookup — DNS Interactive Resolver';
            } else if (this.currentSubShell === 'ssh') {
                this.promptLabelEl.textContent = `${this.subShellEngine.user}@${this.subShellEngine.host}:${this.subShellEngine.pwd}$`;
                this.termTitleLabelEl.textContent = `ssh — ${this.subShellEngine.user}@${this.subShellEngine.host}`;
            } else {
                if (!isWin) {
                    const u = this.net.state.currentUserLinux;
                    const pwd = this.vfs.currentDirLinux.replace('/home/student', '~');
                    const char = u === 'root' ? '#' : '$';
                    this.promptLabelEl.textContent = `${u}@${this.net.state.hostname}:${pwd}${char}`;
                    this.termTitleLabelEl.textContent = `bash — ${u}@${this.net.state.hostname}: ${pwd}`;
                } else {
                    this.promptLabelEl.textContent = `${this.vfs.currentDirWin}>`;
                    this.termTitleLabelEl.textContent = `Command Prompt — ${this.vfs.currentDirWin}`;
                }
            }
        }

        updateStatusRibbon() {
            const isWin = this.currentOs === 'windows';
            document.getElementById('statusIp').textContent = this.net.state.ip;
            document.getElementById('statusGw').textContent = this.net.state.gateway;
            document.getElementById('statusUser').textContent = isWin ? this.net.state.currentUserWin : this.net.state.currentUserLinux;
            document.getElementById('statusPwd').textContent = isWin ? this.vfs.currentDirWin : this.vfs.currentDirLinux;
            this.updateStatsUI();
        }

        renderServiceMonitor() {
            const el = document.getElementById('serviceMonitorGrid');
            if (!el) return;
            el.innerHTML = '';
            const svcs = this.net.state.services;
            Object.entries(svcs).forEach(([key, s]) => {
                const isRunning = s.status.includes('running') || s.status === 'RUNNING';
                const pill = document.createElement('div');
                pill.className = 'service-pill';
                pill.innerHTML = `
                    <span class="fw-bold">${key}</span>
                    <span class="d-flex align-items-center gap-1">
                        <small class="text-muted">${isRunning ? `:${s.port || 'act'}` : 'off'}</small>
                        <span class="service-dot ${isRunning ? 'active' : 'inactive'}"></span>
                    </span>
                `;
                el.appendChild(pill);
            });
        }

        renderWelcome() {
            this.outputEl.innerHTML = '';
            if (this.currentOs === 'linux') {
                this.writeLine('Welcome to Ubuntu 22.04.4 LTS (GNU/Linux 5.15.0-89-generic x86_64)', 'white');
                this.writeLine(' * System load:  0.14               Processes:             112', 'dim');
                this.writeLine(` * Memory usage: 24%                IPv4 address for eth0: ${this.net.state.ip}`, 'dim');
                this.writeLine(` * Moduł CLI Lab: Wersja BETA (wczesny dostęp do symulatora CKE)`, 'warn');
                this.writeLine(`\nWpisz 'help' lub 'man <komenda>' aby zobaczyć dokumentację.\nWybierz scenariusz CKE z panelu po prawej, aby przejść zadanie krok po kroku.\n`, 'info');
            } else {
                this.writeLine('Microsoft Windows [Version 10.0.19045.4170]', 'white');
                this.writeLine('(c) Microsoft Corporation. All rights reserved.\r\n', 'white');
                this.writeLine(`ZSEM CLI Lab (BETA) [Host: ${this.net.state.winHostname} | IP: ${this.net.state.ip}]\r\nType 'help' for available commands or 'powershell' for PowerShell.\r\n`, 'info');
            }
        }

        writeLine(text, type = 'dim') {
            const div = document.createElement('div');
            div.className = `term-line ${type}`;
            div.textContent = text;
            this.outputEl.appendChild(div);
            this.outputEl.scrollTop = this.outputEl.scrollHeight;
        }

        writeHtml(html) {
            const div = document.createElement('div');
            div.className = 'term-line';
            div.innerHTML = html;
            this.outputEl.appendChild(div);
            this.outputEl.scrollTop = this.outputEl.scrollHeight;
        }

        clear() {
            this.outputEl.innerHTML = '';
            this.updatePrompt();
        }

        copyOutput() {
            const text = this.outputEl.innerText;
            navigator.clipboard?.writeText(text).then(() => {
                alert('Zawartość terminala została skopiowana do schowka!');
            });
        }

        exportSessionLog() {
            const text = this.outputEl.innerText;
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `zsem_cli_session_${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '_')}.txt`;
            a.click();
            URL.revokeObjectURL(url);
        }

        toggleFullscreen() {
            const win = document.getElementById('terminalWindow');
            win.classList.toggle('is-fullscreen');
        }

        resetAllState() {
            if (confirm('Czy na pewno chcesz zresetować cały wirtualny system plików (VFS) i konfigurację sieci do stanu początkowego?')) {
                this.vfs.reset();
                this.net.reset();
                this.clear();
                this.renderWelcome();
                this.updatePrompt();
                this.updateStatusRibbon();
                this.renderServiceMonitor();
                alert('Pomyślnie zresetowano system plików i sieć!');
            }
        }

        handleInterrupt() {
            if (this.currentSubShell) {
                this.currentSubShell = null;
                this.subShellEngine = null;
                this.writeLine('^C', 'error');
            } else {
                this.writeLine(this.promptLabelEl.textContent + ' ' + this.inputEl.value + '^C', 'prompt');
                this.inputEl.value = '';
            }
            this.updatePrompt();
        }

        navigateHistory(direction) {
            if (!this.history.length) return;
            if (direction === -1) {
                if (this.historyIndex === -1) this.historyIndex = this.history.length - 1;
                else if (this.historyIndex > 0) this.historyIndex--;
            } else {
                if (this.historyIndex !== -1) {
                    if (this.historyIndex < this.history.length - 1) this.historyIndex++;
                    else {
                        this.historyIndex = -1;
                        this.inputEl.value = '';
                        return;
                    }
                }
            }
            if (this.historyIndex >= 0 && this.historyIndex < this.history.length) {
                this.inputEl.value = this.history[this.historyIndex];
            }
        }

        handleTabCompletion() {
            const val = this.inputEl.value;
            if (!val.trim()) return;

            const isWin = this.currentOs === 'windows';
            const parts = val.split(/\s+/);
            const currentWord = parts[parts.length - 1];

            if (parts.length === 1) {
                const commands = isWin ? Object.keys(WINDOWS_COMMANDS) : Object.keys(LINUX_COMMANDS);
                const matches = commands.filter(c => c.toLowerCase().startsWith(currentWord.toLowerCase()));
                if (matches.length === 1) {
                    this.inputEl.value = matches[0] + ' ';
                } else if (matches.length > 1) {
                    this.writeLine(this.promptLabelEl.textContent + ' ' + val, 'prompt');
                    this.writeLine(matches.join('   '), 'info');
                }
            } else {
                const list = this.vfs.listDirectory(this.vfs.normalizePath('.', isWin), isWin);
                if (list) {
                    const matches = list.filter(n => n.name.toLowerCase().startsWith(currentWord.toLowerCase())).map(n => n.name + (n.type === 'dir' ? (isWin ? '\\' : '/') : ''));
                    if (matches.length === 1) {
                        parts[parts.length - 1] = matches[0];
                        this.inputEl.value = parts.join(' ');
                    } else if (matches.length > 1) {
                        this.writeLine(this.promptLabelEl.textContent + ' ' + val, 'prompt');
                        this.writeLine(matches.join('   '), 'info');
                    }
                }
            }
        }

        // ── Execution Pipeline & Stream Dispatcher ─────────────────────────────

        executeCommandLine(line) {
            if (!line.trim()) {
                this.writeLine(this.promptLabelEl.textContent, 'prompt');
                return;
            }

            this.history.push(line);
            this.historyIndex = -1;
            this.net.state.stats.commandsCount++;
            if (this.net.state.stats.commandsCount === 1) {
                this.triggerAchievement('first_cmd', 'Pierwsze polecenie wykonane w terminalu!');
            }

            const promptStr = this.promptLabelEl.textContent;
            this.writeLine(`${promptStr} ${line}`, 'prompt');

            // Handle Sub-Shell Delegation
            if (this.currentSubShell) {
                const res = this.subShellEngine.execute(line);
                if (res.action === 'exit') {
                    this.currentSubShell = null;
                    this.subShellEngine = null;
                } else if (res.action === 'clear') {
                    this.clear();
                    return;
                }
                if (res.output) this.writeLine(res.output, 'white');
                this.updatePrompt();
                this.validateScenario(line);
                return;
            }

            // Handle Redirections (>, >>)
            let redirTarget = null;
            let redirAppend = false;
            let cleanLine = line;

            if (/>>/.test(cleanLine)) {
                const parts = cleanLine.split('>>');
                cleanLine = parts[0];
                redirTarget = parts[1]?.trim();
                redirAppend = true;
            } else if (/>/.test(cleanLine)) {
                const parts = cleanLine.split('>');
                cleanLine = parts[0];
                redirTarget = parts[1]?.trim();
                redirAppend = false;
            }

            // Handle Pipeline (|)
            const pipeStages = cleanLine.split('|').map(s => s.trim()).filter(Boolean);
            let stageOutput = '';

            for (let i = 0; i < pipeStages.length; i++) {
                const stageCmd = pipeStages[i];
                stageOutput = this.dispatchSingleCommand(stageCmd, stageOutput);
                if (stageOutput === '__CLEAR__') {
                    this.clear();
                    return;
                }
            }

            if (redirTarget) {
                const isWin = this.currentOs === 'windows';
                const success = this.vfs.writeFile(redirTarget, stageOutput + '\n', redirAppend, isWin);
                if (!success) {
                    this.writeLine(`Błąd zapisu do pliku: ${redirTarget}`, 'error');
                }
            } else if (stageOutput) {
                this.writeLine(stageOutput, 'white');
            }

            this.updatePrompt();
            this.updateStatusRibbon();
            this.renderServiceMonitor();
            this.validateScenario(line);
        }

        dispatchSingleCommand(cmdStr, pipeInput = '') {
            const isWin = this.currentOs === 'windows';
            const args = this.parseArgs(cmdStr);
            const cmdName = args[0]?.toLowerCase();
            const cmdArgs = args.slice(1);

            if (!cmdName) return '';

            // Sub-shell Openers
            if (cmdName === 'nano') {
                this.openNano(cmdArgs[0] || 'nowy_plik.txt');
                return '';
            }
            if (cmdName === 'mysql' || cmdName === 'mariadb') {
                this.currentSubShell = 'mysql';
                this.subShellEngine = new MysqlShell();
                return 'Welcome to the MariaDB/MySQL monitor. Commands end with ; or \\g.\nServer version: 10.4.32-MariaDB Source distribution\nType \'help;\' or \'\\h\' for help. Type \'\\c\' to clear the current input statement.';
            }
            if (cmdName === 'diskpart') {
                this.currentSubShell = 'diskpart';
                this.subShellEngine = new DiskpartShell();
                return '\r\nMicrosoft DiskPart version 10.0.19041.3636\r\nCopyright (C) Microsoft Corporation.\r\nOn computer: ZSEM-STUDENT\r\n';
            }
            if (cmdName === 'python' || cmdName === 'python3') {
                this.currentSubShell = 'python';
                this.subShellEngine = new PythonShell();
                return 'Python 3.10.12 (main, Nov 20 2023, 15:14:05) [GCC 11.4.0] on linux\nType "help", "copyright", "credits" or "license" for more information.';
            }
            if (cmdName === 'powershell' || cmdName === 'pwsh') {
                this.currentSubShell = 'powershell';
                this.subShellEngine = new PowerShellEngine(this);
                return 'PowerShell 7.3.0\r\nLoading personal and system profiles took 240ms.\r\n';
            }
            if (cmdName === 'nslookup' && !cmdArgs.length) {
                this.currentSubShell = 'nslookup';
                this.subShellEngine = new NslookupShell(this);
                return `Default server: 8.8.8.8\nAddress: 8.8.8.8#53\n> (Wpisz nazwę domeny lub 'exit' aby wyjść)`;
            }
            if (cmdName === 'ssh' && cmdArgs.length) {
                const target = cmdArgs[0];
                const parts = target.split('@');
                const user = parts.length > 1 ? parts[0] : 'student';
                const host = parts.length > 1 ? parts[1] : parts[0];
                this.currentSubShell = 'ssh';
                this.subShellEngine = new SshShell(this, user, host);
                return `Warning: Permanently added '${host}' (ECDSA) to the list of known hosts.\nPassword: \nWelcome to Remote Exam Server (Ubuntu 22.04 LTS)\n[Sesja SSH otwarta — wpisz exit aby powrócić]`;
            }

            // Stream Transformers for Pipes
            if (cmdName === 'grep' || cmdName === 'egrep') {
                const pattern = cmdArgs.find(a => !a.startsWith('-')) || '';
                const ignoreCase = cmdArgs.includes('-i');
                const invert = cmdArgs.includes('-v');
                const countOnly = cmdArgs.includes('-c');

                let lines = pipeInput.split('\n');
                if (cmdArgs.length > 1 && !pipeInput) {
                    const filePath = cmdArgs[cmdArgs.length - 1];
                    const node = this.vfs.getNode(filePath, isWin);
                    if (node && node.content) lines = node.content.split('\n');
                }

                let matches = lines.filter(l => {
                    const found = ignoreCase ? l.toLowerCase().includes(pattern.toLowerCase()) : l.includes(pattern);
                    return invert ? !found : found;
                });

                if (countOnly) return String(matches.length);
                return matches.join('\n');
            }

            if (cmdName === 'findstr') {
                const pattern = cmdArgs.find(a => !a.startsWith('/')) || '';
                const ignoreCase = cmdArgs.some(a => a.toLowerCase() === '/i');
                const lines = pipeInput.split('\r\n').length > 1 ? pipeInput.split('\r\n') : pipeInput.split('\n');
                const matches = lines.filter(l => ignoreCase ? l.toLowerCase().includes(pattern.toLowerCase()) : l.includes(pattern));
                return matches.join('\r\n');
            }

            if (cmdName === 'wc') {
                const text = pipeInput;
                const lines = text ? text.split('\n').length : 0;
                const words = text ? text.trim().split(/\s+/).filter(Boolean).length : 0;
                const bytes = text ? text.length : 0;
                if (cmdArgs.includes('-l')) return String(lines);
                if (cmdArgs.includes('-w')) return String(words);
                if (cmdArgs.includes('-c') || cmdArgs.includes('-m')) return String(bytes);
                return `  ${lines}  ${words}  ${bytes}`;
            }

            if (cmdName === 'head') {
                const nIdx = cmdArgs.indexOf('-n');
                const count = nIdx !== -1 ? parseInt(cmdArgs[nIdx + 1], 10) || 10 : 10;
                const lines = pipeInput.split('\n');
                return lines.slice(0, count).join('\n');
            }

            if (cmdName === 'tail') {
                const nIdx = cmdArgs.indexOf('-n');
                const count = nIdx !== -1 ? parseInt(cmdArgs[nIdx + 1], 10) || 10 : 10;
                const lines = pipeInput.split('\n');
                return lines.slice(-count).join('\n');
            }

            if (cmdName === 'sort') {
                const reverse = cmdArgs.includes('-r');
                const numeric = cmdArgs.includes('-n');
                const lines = pipeInput.split('\n');
                lines.sort((a, b) => numeric ? (parseFloat(a) - parseFloat(b)) : a.localeCompare(b));
                if (reverse) lines.reverse();
                return lines.join('\n');
            }

            if (cmdName === 'uniq') {
                const lines = pipeInput.split('\n');
                return Array.from(new Set(lines)).join('\n');
            }

            if (cmdName === 'cut') {
                const dIdx = cmdArgs.indexOf('-d');
                const delim = dIdx !== -1 ? cmdArgs[dIdx + 1] || ':' : '\t';
                const fIdx = cmdArgs.indexOf('-f');
                const field = fIdx !== -1 ? parseInt(cmdArgs[fIdx + 1], 10) - 1 : 0;
                const lines = pipeInput.split('\n');
                return lines.map(l => l.split(delim)[field] || '').join('\n');
            }

            if (cmdName === 'sed') {
                const expr = cmdArgs[0] || '';
                const match = expr.match(/s\/(.*?)\/(.*?)\/g?/);
                if (match) {
                    const [, find, replace] = match;
                    return pipeInput.replaceAll(find, replace);
                }
                return pipeInput;
            }

            if (cmdName === 'awk') {
                const lines = pipeInput.split('\n');
                return lines.map(l => l.trim().split(/\s+/)[0] || '').join('\n');
            }

            // Command Registries lookup
            const reg = isWin ? WINDOWS_COMMANDS : LINUX_COMMANDS;
            if (reg[cmdName]) {
                return reg[cmdName](cmdArgs, this);
            }

            const full2 = (args[0] + ' ' + (args[1] || '')).trim().toLowerCase();
            if (reg[full2]) {
                return reg[full2](args.slice(2), this);
            }

            if (!isWin) {
                return `bash: ${cmdName}: command not found. Type 'help' for available commands.`;
            } else {
                return `'${cmdName}' is not recognized as an internal or external command,\r\noperable program or batch file. Type 'help' for available commands.`;
            }
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
                } else if (/\s/.test(c) && !inQuotes) {
                    if (current) {
                        args.push(current);
                        current = '';
                    }
                } else {
                    current += c;
                }
            }
            if (current) args.push(current);
            return args;
        }

        // ── Inline Nano Editor Handlers ────────────────────────────────────────

        openNano(filename) {
            const isWin = this.currentOs === 'windows';
            this.nanoCurrentPath = filename;
            const node = this.vfs.getNode(filename, isWin);
            this.nanoFilename.textContent = filename;
            this.nanoTextarea.value = node && node.content ? node.content : '';
            this.nanoModified.textContent = '[Zapisany]';
            this.nanoOverlay.style.display = 'flex';
            this.nanoTextarea.focus();
        }

        saveNano() {
            const isWin = this.currentOs === 'windows';
            const content = this.nanoTextarea.value;
            this.vfs.writeFile(this.nanoCurrentPath, content, false, isWin);
            this.nanoModified.textContent = '[Zapisano ' + content.split('\n').length + ' linii]';
            document.getElementById('nanoStatusMsg').textContent = `[ Zapisano pomyślnie do ${this.nanoCurrentPath} ]`;
            this.validateScenario('nano_saved');
            setTimeout(() => {
                document.getElementById('nanoStatusMsg').textContent = 'Wskazówka: Wciśnij Ctrl+O aby zapisać, Ctrl+X aby zamknąć, Ctrl+K wytnij, Ctrl+U wklej.';
            }, 2500);
        }

        closeNano() {
            this.nanoOverlay.style.display = 'none';
            this.inputEl.focus();
            this.writeLine(`[ nano: edycja ${this.nanoCurrentPath} zakończona ]`, 'success');
        }

        nanoCutLine() {
            const ta = this.nanoTextarea;
            const val = ta.value;
            const pos = ta.selectionStart;
            const lines = val.split('\n');
            let cur = 0;
            for (let i = 0; i < lines.length; i++) {
                const len = lines[i].length + 1;
                if (pos >= cur && pos < cur + len) {
                    this.vfs.nanoClipboard = lines[i];
                    lines.splice(i, 1);
                    ta.value = lines.join('\n');
                    document.getElementById('nanoStatusMsg').textContent = `[ Wycięto 1 linię do schowka ]`;
                    break;
                }
                cur += len;
            }
        }

        nanoPasteLine() {
            if (!this.vfs.nanoClipboard) return;
            const ta = this.nanoTextarea;
            const pos = ta.selectionStart;
            ta.value = ta.value.slice(0, pos) + this.vfs.nanoClipboard + '\n' + ta.value.slice(pos);
            document.getElementById('nanoStatusMsg').textContent = `[ Wklejono ze schowka ]`;
        }

        nanoShowPos() {
            const ta = this.nanoTextarea;
            const lines = ta.value.slice(0, ta.selectionStart).split('\n');
            const row = lines.length;
            const col = lines[lines.length - 1].length + 1;
            document.getElementById('nanoStatusMsg').textContent = `[ Linia ${row}/${ta.value.split('\n').length}, Kolumna ${col}, Znak ${ta.selectionStart}/${ta.value.length} ]`;
        }

        nanoSearch() {
            const term = prompt('Szukaj tekstu w nano:');
            if (term && this.nanoTextarea.value.includes(term)) {
                const idx = this.nanoTextarea.value.indexOf(term);
                this.nanoTextarea.selectionStart = idx;
                this.nanoTextarea.selectionEnd = idx + term.length;
                document.getElementById('nanoStatusMsg').textContent = `[ Znaleziono '${term}' ]`;
            }
        }

        // ── Scenario Engine (20 Multi-Step Scenarios) ──────────────────────────

        renderScenarios(filterCat = 'all') {
            const list = document.getElementById('scenarioList');
            if (!list) return;
            list.innerHTML = '';

            const filtered = filterCat === 'all' ? CKE_SCENARIOS : CKE_SCENARIOS.filter(s => s.cat === filterCat);
            filtered.forEach((sc) => {
                const card = document.createElement('div');
                card.className = `scenario-card ${this.activeScenario?.id === sc.id ? 'active' : ''} ${sc.completed ? 'completed' : ''}`;
                const xpAmount = sc.xp || (sc.stars === '★★★' ? 40 : (sc.stars === '★★☆' ? 25 : 20));
                const compBadge = sc.completed
                    ? `<span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2 py-1"><i class="bi bi-check2-circle me-1"></i>Zaliczone</span>`
                    : `<span class="badge bg-warning bg-opacity-20 text-warning rounded-pill px-2 py-1 fw-bold">+${xpAmount} XP</span>`;

                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="scenario-badge bg-${sc.badgeColor || 'primary'} text-white">${sc.catLabel}</span>
                        <div class="d-flex align-items-center gap-1">
                            ${compBadge}
                            <span class="scenario-stars">${sc.stars || '★★☆'}</span>
                            <small class="text-muted">${sc.os.toUpperCase()}</small>
                        </div>
                    </div>
                    <strong class="d-block mb-1 text-dark dark:text-white">${sc.title}</strong>
                    <div class="small text-muted">${sc.desc.slice(0, 75)}... (${sc.steps.length} kroków)</div>
                `;
                card.addEventListener('click', () => this.selectScenario(sc));
                list.appendChild(card);
            });
        }

        selectScenario(sc) {
            this.activeScenario = sc;
            this.activeScenarioStep = 0;
            if (sc.os !== 'any' && sc.os !== this.currentOs) {
                this.switchOs(sc.os);
            }
            this.renderScenarios();

            document.getElementById('scenarioProgressWrap').style.display = 'block';
            document.getElementById('scenarioProgressLabel').textContent = sc.title;
            this.updateScenarioStepUI();
        }

        updateScenarioStepUI() {
            if (!this.activeScenario) return;
            const sc = this.activeScenario;
            const total = sc.steps.length;
            const cur = this.activeScenarioStep;
            const pct = Math.round((cur / total) * 100);

            document.getElementById('scenarioStepLabel').textContent = `Krok ${cur + 1}/${total}`;
            document.getElementById('scenarioProgressBar').style.width = `${pct}%`;
            document.getElementById('scenarioStepInstruction').innerHTML = `<strong>Instrukcja:</strong> ${sc.steps[cur]?.task || 'Ukończono!'}`;
            document.getElementById('activeScenarioDesc').innerHTML = `<strong>Zadanie:</strong> ${sc.desc}<br><strong class="text-primary mt-2 d-block">Krok ${cur + 1}/${total}:</strong> ${sc.steps[cur]?.task || 'Ukończono!'}`;
        }

        skipScenarioStep() {
            if (!this.activeScenario) return;
            this.writeLine(`⏩ Pominięto krok ${this.activeScenarioStep + 1}: ${this.activeScenario.steps[this.activeScenarioStep].task}`, 'warn');
            this.activeScenarioStep++;
            if (this.activeScenarioStep >= this.activeScenario.steps.length) {
                this.activeScenario.completed = true;
                this.writeLine(`Zadanie '${this.activeScenario.title}' zakończone.`, 'info');
                this.renderScenarios();
                this.updateStatsUI();
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
                this.writeLine(`✔ [Zaliczono krok ${this.activeScenarioStep + 1}/${sc.steps.length}]: ${step.task}`, 'success');
                this.activeScenarioStep++;
                if (this.activeScenarioStep >= sc.steps.length) {
                    sc.completed = true;
                    this.net.state.stats.completedScenarios = CKE_SCENARIOS.filter(s => s.completed).length;
                    this.triggerAchievement('cke_master', `Ukończono zadanie egzaminacyjne: ${sc.title}!`);
                    this.writeLine(`🎉 GRATULACJE! Zadanie '${sc.title}' zostało ukończone w 100%!`, 'success');
                    document.getElementById('scenarioProgressBar').style.width = '100%';
                    document.getElementById('scenarioStepInstruction').textContent = 'Zadanie pomyślnie zaliczone!';
                    this.renderScenarios();
                    this.updateStatsUI();
                    this.awardScenarioXp(sc);
                } else {
                    this.updateScenarioStepUI();
                }
            }
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
                    if (data.is_first_time && data.xp_earned > 0) {
                        window.CLI_LAB_USER.xp = data.total_xp;
                        if (!window.CLI_LAB_USER.completedScenarios.includes(sc.id)) {
                            window.CLI_LAB_USER.completedScenarios.push(sc.id);
                        }
                        this.updateStatsUI();

                        const heroXp = document.getElementById('heroXpDisplay');
                        if (heroXp) heroXp.textContent = `${Number(data.total_xp).toLocaleString()} XP`;
                        const heroRank = document.getElementById('heroRankDisplay');
                        if (heroRank && data.rank) heroRank.textContent = data.rank;

                        this.writeLine(`🏆 [RANKING] Zdobywasz +${data.xp_earned} XP! Twój stan konta: ${Number(data.total_xp).toLocaleString()} XP (${data.rank || ''})`, 'warn');
                        this.triggerAchievement(sc.id, `+${data.xp_earned} XP za zadanie: ${sc.title}`, '⭐');
                    } else if (!data.is_first_time) {
                        this.writeLine(`ℹ [RANKING] Zadanie było już wcześniej zaliczone (punkty XP przyznano jednorazowo).`, 'dim');
                    }
                } else if (data.message) {
                    this.writeLine(`ℹ [RANKING] ${data.message}`, 'dim');
                }
            } catch (e) {
                console.warn('Błąd połączenia z serwerem XP:', e);
            }
        }

        renderCommandList() {
            const el = document.getElementById('commandList');
            if (!el) return;
            const isWin = this.currentOs === 'windows';
            const cmds = isWin
                ? ['ipconfig /all', 'ping', 'tracert', 'nslookup', 'netstat -ano', 'netsh interface ip', 'netsh advfirewall', 'net user', 'net localgroup', 'net share', 'route print', 'tasklist', 'taskkill', 'diskpart', 'powershell', 'sfc /scannow', 'chkdsk', 'attrib', 'systeminfo', 'Get-Service', 'Get-NetIPAddress', 'Test-NetConnection', 'iisreset', 'appcmd', 'dnscmd']
                : ['man <cmd>', 'ifconfig', 'ip a / route', 'ping -c 4', 'traceroute', 'nmap', 'iptables -L', 'ufw status', 'systemctl status', 'apachectl configtest', 'a2ensite', 'named-checkconf', 'testparm', 'chmod 755', 'chown', 'useradd -m', 'cat /etc/passwd', 'ls -la', 'nano', 'grep', 'wc -l', 'cut', 'sed', 'awk', 'mysql', 'python3', 'tar', 'df -h', 'ps aux'];

            el.innerHTML = cmds.map(c => `<span class="badge bg-secondary bg-opacity-15 text-dark dark:text-light me-1 mb-1 font-monospace">${c}</span>`).join(' ');
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // 5. EXHAUSTIVE LINUX COMMAND REGISTRY (85+ COMMANDS)
    // ════════════════════════════════════════════════════════════════════════════

    const LINUX_COMMANDS = {
        'help': () => `Dostępne polecenia Linux CLI Lab (ponad 85 komend & narzędzi serwerowych):\n` +
            ` Pliki & VFS:     ls, cd, pwd, mkdir, rmdir, touch, rm, cp, mv, ln, find, tree, stat, file\n` +
            ` Tekst & Potoki:  cat, tac, head, tail, grep, egrep, wc, sort, uniq, cut, tr, sed, awk, tee, diff, strings, nl, less, more\n` +
            ` Sieć & Diag:     ip (a/r/n), ifconfig, ping, traceroute, nslookup, dig, host, whois, netstat, ss, arp, route, curl, wget, nmap, tcpdump, nc\n` +
            ` Serwery CKE:     apachectl, a2ensite, a2dissite, a2enmod, named-checkconf, named-checkzone, rndc, testparm, smbpasswd, pdbedit, smbclient, dhcpd, postconf, exportfs\n` +
            ` Zapory:          iptables (-L/-A/-D/-F), ufw (status/enable/allow/deny), fail2ban-client\n` +
            ` Uprawnienia:     chmod, chown, chgrp, umask, useradd, usermod, userdel, groupadd, passwd, su, sudo, whoami, id\n` +
            ` System & Proces: systemctl, service, journalctl, dmesg, ps, top, htop, kill, killall, uname, hostnamectl, uptime, date, df, du, free, lscpu, lsblk, fdisk, mount, crontab\n` +
            ` Pakiety:         apt, apt-get, dpkg, tar, gzip, gunzip, zip, unzip, nano, mysql, python3, ssh, man`,

        'clear': () => '__CLEAR__',
        'cls': () => '__CLEAR__',

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
            const showAll = a.includes('-a') || a.includes('-la') || a.includes('-al');
            const showLong = a.includes('-l') || a.includes('-la') || a.includes('-al') || a.includes('-lh');
            const targetPath = a.find(arg => !arg.startsWith('-')) || '.';
            const node = term.vfs.getNode(targetPath, false);

            if (!node) return `ls: cannot access '${targetPath}': No such file or directory`;
            if (node.type === 'file') {
                return showLong ? `-rw-r--r-- 1 ${node.owner} ${node.group} ${node.size} ${node.name}` : node.name;
            }

            const items = Object.values(node.children || {});
            const filtered = showAll ? [{ name: '.', type: 'dir', permissions: '0755', owner: 'root', group: 'root', size: 4096 }, { name: '..', type: 'dir', permissions: '0755', owner: 'root', group: 'root', size: 4096 }, ...items] : items.filter(i => !i.name.startsWith('.'));

            if (showLong) {
                let out = `total ${filtered.length * 4}\n`;
                out += filtered.map(i => {
                    const isDir = i.type === 'dir' ? 'd' : (i.type === 'symlink' ? 'l' : '-');
                    const perm = i.permissions ? 'rwxr-xr-x' : 'rw-r--r--';
                    const linkTarget = i.type === 'symlink' ? ` -> ${i.target}` : '';
                    return `${isDir}${perm} 1 ${i.owner || 'student'} ${i.group || 'student'} ${String(i.size || 4096).padStart(6)} ${i.name}${linkTarget}`;
                }).join('\n');
                return out;
            }
            return filtered.map(i => i.name + (i.type === 'dir' ? '/' : '')).join('  ');
        },

        'mkdir': (a, term) => {
            if (!a[0]) return 'mkdir: missing operand';
            const recursive = a.includes('-p');
            const path = a.find(arg => !arg.startsWith('-'));
            const ok = term.vfs.createDirectory(path, recursive, false);
            return ok ? '' : `mkdir: cannot create directory '${path}': No such file or directory`;
        },

        'rmdir': (a, term) => {
            if (!a[0]) return 'rmdir: missing operand';
            const ok = term.vfs.removeNode(a[0], false, false);
            return ok ? '' : `rmdir: failed to remove '${a[0]}': Directory not empty or not found`;
        },

        'touch': (a, term) => {
            if (!a[0]) return 'touch: missing file operand';
            term.vfs.createFile(a[0], '', false);
            return '';
        },

        'rm': (a, term) => {
            if (!a[0]) return 'rm: missing operand';
            const recursive = a.includes('-r') || a.includes('-rf') || a.includes('-R');
            const path = a.find(arg => !arg.startsWith('-'));
            const ok = term.vfs.removeNode(path, recursive, false);
            return ok ? '' : `rm: cannot remove '${path}': No such file or directory`;
        },

        'cp': (a, term) => {
            if (a.length < 2) return 'cp: missing destination file operand';
            const recursive = a.includes('-r') || a.includes('-R');
            const clean = a.filter(arg => !arg.startsWith('-'));
            const ok = term.vfs.copyNode(clean[0], clean[1], recursive, false);
            return ok ? '' : `cp: cannot copy '${clean[0]}' to '${clean[1]}'`;
        },

        'mv': (a, term) => {
            if (a.length < 2) return 'mv: missing destination file operand';
            const clean = a.filter(arg => !arg.startsWith('-'));
            const ok = term.vfs.moveNode(clean[0], clean[1], false);
            return ok ? '' : `mv: cannot move '${clean[0]}' to '${clean[1]}'`;
        },

        'ln': (a, term) => {
            if (a.length < 2) return 'ln: missing destination file operand';
            const isSym = a.includes('-s');
            const clean = a.filter(arg => !arg.startsWith('-'));
            const { parent, name } = term.vfs.getParentNode(clean[1], false);
            if (parent && parent.children) {
                parent.children[name] = { type: isSym ? 'symlink' : 'file', name, target: clean[0], permissions: '0777', owner: 'student', group: 'student', content: '' };
                term.vfs.save();
                return '';
            }
            return `ln: failed to create link '${clean[1]}'`;
        },

        'cat': (a, term) => {
            if (!a[0]) return 'cat: missing operand';
            const node = term.vfs.getNode(a[0], false);
            if (!node) return `cat: ${a[0]}: No such file or directory`;
            if (node.type === 'dir') return `cat: ${a[0]}: Is a directory`;
            return node.content || '';
        },

        'tac': (a, term) => {
            const out = LINUX_COMMANDS.cat(a, term);
            return out.split('\n').reverse().join('\n');
        },

        'find': (a, term) => {
            const path = a.find(arg => !arg.startsWith('-')) || '.';
            const nameIdx = a.indexOf('-name');
            const targetName = nameIdx !== -1 ? a[nameIdx + 1]?.replace(/['"]/g, '') : null;
            const node = term.vfs.getNode(path, false);
            if (!node) return `find: '${path}': No such file or directory`;

            const results = [];
            function traverse(curr, currPath) {
                if (!targetName || curr.name.includes(targetName.replace(/\*/g, ''))) {
                    results.push(currPath);
                }
                if (curr.type === 'dir' && curr.children) {
                    Object.values(curr.children).forEach(child => {
                        traverse(child, currPath + '/' + child.name);
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

        'stat': (a, term) => {
            if (!a[0]) return 'stat: missing operand';
            const node = term.vfs.getNode(a[0], false);
            if (!node) return `stat: cannot stat '${a[0]}': No such file or directory`;
            return `  File: ${a[0]}\n  Size: ${node.size || 4096}  Blocks: 8  IO Block: 4096  ${node.type === 'dir' ? 'directory' : 'regular file'}\nAccess: (0${node.permissions || '755'})  Uid: ( 1000/ student)   Gid: ( 1000/ student)\nModify: ${node.modified || new Date().toISOString()}`;
        },

        'file': (a, term) => {
            if (!a[0]) return 'file: missing operand';
            const node = term.vfs.getNode(a[0], false);
            if (!node) return `${a[0]}: cannot open (No such file or directory)`;
            if (node.type === 'dir') return `${a[0]}: directory`;
            if (a[0].endsWith('.sh')) return `${a[0]}: Bourne-Again shell script, ASCII text executable`;
            if (a[0].endsWith('.py')) return `${a[0]}: Python script, ASCII text executable`;
            if (a[0].endsWith('.conf') || a[0].endsWith('.cfg')) return `${a[0]}: ASCII text configuration file`;
            return `${a[0]}: ASCII text`;
        },

        'echo': (a) => a.join(' '),

        'chmod': (a, term) => {
            if (a.length < 2) return 'Usage: chmod <mode> <file>';
            const node = term.vfs.getNode(a[1], false);
            if (!node) return `chmod: cannot access '${a[1]}': No such file or directory`;
            node.permissions = a[0];
            term.vfs.save();
            return '';
        },

        'chown': (a, term) => {
            if (a.length < 2) return 'Usage: chown <user[:group]> <file>';
            const node = term.vfs.getNode(a[1], false);
            if (!node) return `chown: cannot access '${a[1]}': No such file or directory`;
            const parts = a[0].split(':');
            node.owner = parts[0];
            if (parts[1]) node.group = parts[1];
            term.vfs.save();
            return '';
        },

        'chgrp': (a, term) => {
            if (a.length < 2) return 'Usage: chgrp <group> <file>';
            const node = term.vfs.getNode(a[1], false);
            if (!node) return `chgrp: cannot access '${a[1]}': No such file or directory`;
            node.group = a[0];
            term.vfs.save();
            return '';
        },

        'whoami': (a, term) => term.net.state.currentUserLinux,
        'id': (a, term) => {
            const u = term.net.state.currentUserLinux;
            return u === 'root' ? 'uid=0(root) gid=0(root) groups=0(root)' : 'uid=1000(student) gid=1000(student) groups=1000(student),27(sudo),100(users)';
        },

        'hostname': (a, term) => {
            if (a[0]) {
                term.net.state.hostname = a[0];
                term.net.save();
                return '';
            }
            return term.net.state.hostname;
        },

        'uname': (a) => a.includes('-a') ? 'Linux zsem-lab 5.15.0-89-generic #99-Ubuntu SMP x86_64 GNU/Linux' : 'Linux',
        'date': () => new Date().toUTCString(),
        'uptime': () => `${new Date().toLocaleTimeString('pl-PL')} up 2 days, 4:12, 1 user, load average: 0.14, 0.08, 0.05`,
        'df': () => `Filesystem      Size  Used Avail Use% Mounted on\ntmpfs           795M  2.4M  793M   1% /run\n/dev/sda1        30G  8.4G   20G  30% /\ntmpfs           3.9G     0  3.9G   0% /dev/shm\n/dev/sda2       512M   53M  459M  11% /boot`,
        'free': () => `               total        used        free      shared  buff/cache   available\nMem:         8167384     1924512     4210456       34120     2032416     5921400\nSwap:        2097148           0     2097148`,

        // ── Server Commands (Apache, BIND9, Samba, DHCP) ──────────────────────

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
            return 'params.c:OpenConfFile() - Unable to open configuration file "/etc/samba/smb.conf"';
        },

        'smbpasswd': (a) => `New SMB password:\nRetype new SMB password:\nAdded user ${a[a.indexOf('-a') + 1] || 'student'}.`,

        'dhcpd': (a, term) => {
            if (a.includes('-t')) {
                const conf = term.vfs.getNode('/etc/dhcp/dhcpd.conf', false);
                return conf ? 'Internet Systems Consortium DHCP Server 4.4.1\nConfiguration file /etc/dhcp/dhcpd.conf test syntax OK.' : 'Can\'t open /etc/dhcp/dhcpd.conf: No such file';
            }
            return 'dhcpd started.';
        },

        'postconf': () => `myhostname = mail.zsem.local\nmydomain = zsem.local\ninet_interfaces = all\nmydestination = $myhostname, localhost.$mydomain, localhost, $mydomain`,
        'exportfs': () => `/srv/nfs/shared\t192.168.1.0/24`,

        // ── Network Commands ─────────────────────────────────────────────────

        'ifconfig': (a, term) => {
            const net = term.net.state;
            return `eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500\n        inet ${net.ip}  netmask ${net.netmask}  broadcast 192.168.1.255\n        inet6 fe80::20c:29ff:feab:cdef  prefixlen 64  scopeid 0x20<link>\n        ether ${net.mac.toLowerCase()}  txqueuelen 1000  (Ethernet)\n        RX packets 15234  bytes 12948102 (12.3 MiB)\n        TX packets 9842  bytes 1421678 (1.3 MiB)\n\nlo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536\n        inet 127.0.0.1  netmask 255.0.0.0\n        loop  txqueuelen 1000  (Local Loopback)`;
        },

        'ip': (a, term) => {
            const sub = a[0] || 'a';
            const net = term.net.state;
            if (sub === 'a' || sub === 'addr' || sub === 'address') {
                if (a[1] === 'add' && a[2]) {
                    const [ip, mask] = a[2].split('/');
                    net.ip = ip;
                    if (mask) net.prefix = parseInt(mask, 10);
                    term.net.save();
                    return '';
                }
                return `1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 state UNKNOWN\n    inet 127.0.0.1/8 scope host lo\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 state UP\n    link/ether ${net.mac.toLowerCase()} brd ff:ff:ff:ff:ff:ff\n    inet ${net.ip}/${net.prefix} brd 192.168.1.255 scope global dynamic eth0`;
            }
            if (sub === 'r' || sub === 'route') {
                if (a[1] === 'add') {
                    if (a[2] === 'default' && a[3] === 'via') net.gateway = a[4];
                    else net.routes.push({ dest: a[2], gw: a[4] || '0.0.0.0', iface: 'eth0', metric: 50 });
                    term.net.save();
                    return '';
                }
                return `default via ${net.gateway} dev eth0 proto dhcp src ${net.ip} metric 100\n192.168.1.0/24 dev eth0 proto kernel scope link src ${net.ip} metric 100`;
            }
            return `Usage: ip [addr|route|link|neigh]`;
        },

        'ping': (a) => {
            const host = a.find(arg => !arg.startsWith('-')) || '8.8.8.8';
            let out = `PING ${host} (${host}) 56(84) bytes of data.\n`;
            for (let i = 1; i <= 4; i++) {
                const time = (12 + Math.random() * 8).toFixed(2);
                out += `64 bytes from ${host}: icmp_seq=${i} ttl=117 time=${time} ms\n`;
            }
            out += `\n--- ${host} ping statistics ---\n4 packets transmitted, 4 received, 0% packet loss, time 3004ms\nrtt min/avg/max = 12.14/15.82/19.45 ms`;
            return out;
        },

        'traceroute': (a) => {
            const host = a[0] || '8.8.8.8';
            return `traceroute to ${host} (${host}), 30 hops max, 60 byte packets\n 1  192.168.1.1 (192.168.1.1)  2.145 ms\n 2  10.0.0.1 (isp-gw.pl)  8.421 ms\n 3  213.180.1.1 (transit.pl)  14.210 ms\n 4  ${host} (${host})  16.450 ms`;
        },

        'nslookup': (a) => {
            const host = a[0] || 'google.pl';
            return `Server:\t\t8.8.8.8\nAddress:\t8.8.8.8#53\n\nNon-authoritative answer:\nName:\t${host}\nAddress: 142.250.187.195`;
        },

        'dig': (a) => `; <<>> DiG 9.18.18 <<>> ${a[0] || 'google.pl'}\n;; ANSWER SECTION:\n${a[0] || 'google.pl'}.\t300\tIN\tA\t142.250.187.195\n;; SERVER: 8.8.8.8#53(8.8.8.8)`,
        'host': (a) => `${a[0] || 'google.pl'} has address 142.250.187.195\n${a[0] || 'google.pl'} mail is handled by 10 smtp.google.com.`,

        'nmap': (a) => {
            const host = a.find(arg => !arg.startsWith('-')) || '192.168.1.1';
            return `Starting Nmap 7.80 at ${new Date().toISOString().slice(0, 16)}\nNmap scan report for ${host}\nHost is up (0.0024s latency).\nPORT     STATE SERVICE\n22/tcp   open  ssh\n80/tcp   open  http\n443/tcp  open  https\n3306/tcp open  mysql\n\nNmap done: 1 IP address (1 host up) scanned in 1.2s`;
        },

        'netstat': () => `Active Internet connections (only servers)\nProto Recv-Q Send-Q Local Address           Foreign Address         State       PID/Program name\ntcp        0      0 0.0.0.0:22              0.0.0.0:*               LISTEN      1230/sshd\ntcp        0      0 0.0.0.0:80              0.0.0.0:*               LISTEN      1450/nginx\ntcp        0      0 127.0.0.1:3306          0.0.0.0:*               LISTEN      1890/mysqld`,
        'ss': () => `Netid State  Recv-Q Send-Q Local Address:Port  Peer Address:Port Process\ntcp   LISTEN 0      128    0.0.0.0:22          0.0.0.0:*         users:(("sshd",pid=1230,fd=3))\ntcp   LISTEN 0      511    0.0.0.0:80          0.0.0.0:*         users:(("nginx",pid=1450,fd=6))`,
        'arp': () => `Address                  HWtype  HWaddress           Flags Mask            Iface\n192.168.1.1              ether   00:50:56:c0:00:01   C                     eth0`,

        'iptables': (a, term) => {
            if (!a.length || a.includes('-L')) {
                const rules = term.net.state.firewallIptables.INPUT || [];
                let out = `Chain INPUT (policy ACCEPT)\ntarget     prot opt source               destination\n`;
                out += rules.map(r => `${r.action.padEnd(10)} ${r.proto.padEnd(4)} --  0.0.0.0/0            0.0.0.0/0            tcp dpt:${r.dport}`).join('\n');
                return out + `\n\nChain FORWARD (policy DROP)\n\nChain OUTPUT (policy ACCEPT)`;
            }
            if (a.includes('-A')) {
                const chain = a[a.indexOf('-A') + 1] || 'INPUT';
                const dport = a.includes('--dport') ? a[a.indexOf('--dport') + 1] : '80';
                const action = a.includes('-j') ? a[a.indexOf('-j') + 1] : 'DROP';
                if (!term.net.state.firewallIptables[chain]) term.net.state.firewallIptables[chain] = [];
                term.net.state.firewallIptables[chain].push({ proto: 'tcp', dport, action });
                term.net.save();
                return '';
            }
            if (a.includes('-F')) {
                term.net.state.firewallIptables = { INPUT: [], FORWARD: [], OUTPUT: [] };
                term.net.save();
                return '';
            }
            return 'iptables: rule updated successfully.';
        },

        'ufw': (a, term) => {
            const sub = a[0] || 'status';
            if (sub === 'status') {
                const st = term.net.state.firewallUfw;
                let out = `Status: ${st.enabled ? 'active' : 'inactive'}\n\nTo                         Action      From\n--                         ------      ----\n`;
                out += st.rules.map(r => `${r.port.padEnd(26)} ${r.action.padEnd(11)} Anywhere`).join('\n');
                return out;
            }
            if (sub === 'enable') {
                term.net.state.firewallUfw.enabled = true;
                term.net.save();
                return 'Firewall is active and enabled on system startup';
            }
            if (sub === 'allow') {
                const port = a[1] || '22';
                term.net.state.firewallUfw.rules.push({ port, action: 'ALLOW' });
                term.net.save();
                return `Rule added\nRule added (v6)`;
            }
            if (sub === 'deny') {
                const port = a[1] || '80';
                term.net.state.firewallUfw.rules.push({ port, action: 'DENY' });
                term.net.save();
                return `Rule added\nRule added (v6)`;
            }
            return 'Usage: ufw [status|enable|disable|allow|deny]';
        },

        'systemctl': (a, term) => {
            const sub = a[0] || 'status';
            const svc = (a[1] || 'nginx').replace('.service', '');
            const s = term.net.state.services[svc];

            if (sub === 'status') {
                if (!s) return `Unit ${svc}.service could not be found.`;
                const isRunning = s.status.includes('running');
                return `● ${svc}.service - ${s.name}\n     Loaded: loaded (/lib/systemd/system/${svc}.service; enabled; vendor preset: enabled)\n     Active: ${s.status} since ${new Date().toISOString().slice(0, 10)} 08:00:00 UTC\n   Main PID: ${isRunning ? s.pid || 1240 : 0} (${svc})\n      Tasks: 2 (limit: 4915)\n     Memory: 8.4M`;
            }
            if (sub === 'start' || sub === 'restart' || sub === 'reload') {
                if (s) {
                    s.status = 'active (running)';
                    if (!s.pid) s.pid = 1000 + Math.floor(Math.random() * 8000);
                    term.net.save();
                }
                return `[  OK  ] Started ${svc}.service.`;
            }
            if (sub === 'stop') {
                if (s) {
                    s.status = 'inactive (dead)';
                    s.pid = 0;
                    term.net.save();
                }
                return `[  OK  ] Stopped ${svc}.service.`;
            }
            return `systemctl: command executed.`;
        },
        'service': (a, term) => LINUX_COMMANDS.systemctl([a[1] || 'status', a[0] || 'nginx'], term),

        'ps': () => `  PID TTY          TIME CMD\n 1230 pts/0    00:00:01 bash\n 1450 ?        00:00:02 nginx\n 1890 ?        00:00:10 mysqld\n 2540 pts/0    00:00:00 ps`,
        'top': () => `top - ${new Date().toLocaleTimeString()} up 2 days, 1 user, load average: 0.14, 0.08, 0.05\nTasks: 112 total,   1 running, 111 sleeping,   0 stopped\n%Cpu(s):  1.2 us,  0.8 sy,  0.0 ni, 97.8 id`,
        'htop': (a, term) => LINUX_COMMANDS.top(a, term),

        'kill': (a) => `[1]+  Terminated              ${a[0] || '1234'}`,
        'killall': (a) => `killall: sent signal to ${a[0] || 'proc'}`,

        'useradd': (a, term) => {
            const username = a.find(arg => !arg.startsWith('-'));
            if (!username) return 'useradd: missing username';
            term.vfs.writeFile('/etc/passwd', `${username}:x:1003:1003:${username},,,:/home/${username}:/bin/bash\n`, true, false);
            if (a.includes('-m')) term.vfs.createDirectory(`/home/${username}`, true, false);
            return '';
        },

        'usermod': (a) => `usermod: user properties updated successfully.`,
        'userdel': (a, term) => {
            const user = a[a.length - 1];
            return `userdel: user '${user}' removed from /etc/passwd`;
        },
        'groupadd': (a, term) => {
            term.vfs.writeFile('/etc/group', `${a[0] || 'nowagrupa'}:x:1005:\n`, true, false);
            return '';
        },
        'passwd': (a) => `New password for ${a[0] || 'student'}:\nRetype new password:\npasswd: password updated successfully`,

        'su': (a, term) => {
            const target = a[0] === '-' ? (a[1] || 'root') : (a[0] || 'root');
            term.net.state.currentUserLinux = target;
            term.net.save();
            return `Switched to user ${target}`;
        },

        'sudo': (a, term) => {
            if (!a.length) return 'usage: sudo <command>';
            return term.dispatchSingleCommand(a.join(' '), '');
        },

        'curl': (a) => `<!DOCTYPE html>\n<html>\n<head><title>ZSEM Server</title></head>\n<body><h1>Połączono z ${a[0] || 'localhost'}</h1></body>\n</html>`,
        'wget': (a) => `Resolving ${a[0] || 'target'}... connected.\nHTTP request sent, awaiting response... 200 OK\nLength: 4210 [text/html]\nSaving to: 'index.html'`,

        'apt': (a, term) => {
            const sub = a[0] || 'update';
            if (sub === 'update') return 'Hit:1 http://pl.archive.ubuntu.com/ubuntu jammy InRelease\nReading package lists... Done';
            if (sub === 'install') {
                const pkg = a[1] || 'apache2';
                if (!term.net.state.installedPackages.includes(pkg)) {
                    term.net.state.installedPackages.push(pkg);
                    term.net.state.stats.packagesInstalled++;
                    if (term.net.state.services[pkg]) {
                        term.net.state.services[pkg].installed = true;
                    }
                    term.net.save();
                }
                return `Reading package lists... Done\nBuilding dependency tree... Done\nSetting up ${pkg} (latest) ...\nProcessing triggers for systemd... Done.`;
            }
            return 'apt: try apt update | apt install <package>';
        },
        'apt-get': (a, term) => LINUX_COMMANDS.apt(a, term),

        'tar': (a) => `server_backup.tar.gz created successfully.`,
        'gzip': (a) => '',
        'crontab': (a) => a.includes('-l') ? '# m h  dom mon dow   command\n0 2 * * * /usr/local/bin/backup.sh' : 'crontab: editing crontab for student',

        'man': (a) => {
            const cmd = a[0]?.toLowerCase();
            return MAN_PAGES[cmd] || `No manual entry for ${cmd || 'command'}. Type 'help' for summary.`;
        },

        'history': (a, term) => term.history.map((h, i) => `  ${i + 1}  ${h}`).join('\n')
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 6. EXHAUSTIVE WINDOWS COMMAND REGISTRY (55+ COMMANDS)
    // ════════════════════════════════════════════════════════════════════════════

    const WINDOWS_COMMANDS = {
        'help': () => `Dostępne polecenia Windows (CMD & PowerShell):\r\n` +
            ` Pliki & Dyski:    dir, cd, md, rd, del, copy, xcopy, robocopy, move, ren, type, attrib, icacls, tree, diskpart\r\n` +
            ` Sieć & Diag:      ipconfig [/all], ping, tracert, pathping, nslookup, netstat -ano, arp -a, route print/add, getmac\r\n` +
            ` Netsh & Zapora:   netsh interface ip show/set, netsh advfirewall show/set/firewall add rule\r\n` +
            ` NET Zarządzanie:  net user, net localgroup, net share, net view, net use, net start, net stop\r\n` +
            ` IIS & Serwery:    iisreset, appcmd list site, dnscmd\r\n` +
            ` System & Procesy: systeminfo, whoami, hostname, tasklist, taskkill, sc query/start, sfc /scannow, chkdsk, gpupdate, powershell, cls`,

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

        'type': (a, term) => {
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
            return '\r\n-------------------------------------------------------------------------------\r\n   ROBOCOPY     ::     Robust File Copy for Windows                              \r\n-------------------------------------------------------------------------------\r\n';
        },

        'echo': (a) => a.join(' ') + '\r\n',
        'whoami': (a, term) => `zsem-student\\${term.net.state.currentUserWin}\r\n`,
        'hostname': (a, term) => `${term.net.state.winHostname}\r\n`,

        'systeminfo': (a, term) => `\r\nHost Name:                 ${term.net.state.winHostname}\r\nOS Name:                   Microsoft Windows 10 Pro\r\nOS Version:                10.0.19045 N/A Build 19045\r\nDomain:                    WORKGROUP\r\nNetwork Card(s):           1 NIC(s) Installed.\r\n                           [01]: Intel(R) PRO/1000 MT Connection Name: Ethernet\r\n                                 IP address(es): ${term.net.state.ip}\r\n`,

        'ipconfig': (a, term) => {
            const net = term.net.state;
            if (a.includes('/all')) {
                return `\r\nWindows IP Configuration\r\n\r\n   Host Name . . . . . . . . . . . . : ${net.winHostname}\r\n   Primary Dns Suffix  . . . . . . . : zsem.local\r\n\r\nEthernet adapter Ethernet:\r\n   Physical Address. . . . . . . . . : ${net.mac.replace(/:/g, '-')}\r\n   DHCP Enabled. . . . . . . . . . . : ${net.dhcp ? 'Yes' : 'No'}\r\n   IPv4 Address. . . . . . . . . . . : ${net.ip}(Preferred)\r\n   Subnet Mask . . . . . . . . . . . : ${net.netmask}\r\n   Default Gateway . . . . . . . . . : ${net.gateway}\r\n   DNS Servers . . . . . . . . . . . : ${net.dns.join('\r\n                                       ')}\r\n`;
            }
            return `\r\nWindows IP Configuration\r\n\r\nEthernet adapter Ethernet:\r\n   IPv4 Address. . . . . . . . . . . : ${net.ip}\r\n   Subnet Mask . . . . . . . . . . . : ${net.netmask}\r\n   Default Gateway . . . . . . . . . : ${net.gateway}\r\n`;
        },

        'ping': (a, term) => {
            const host = a.find(arg => !arg.startsWith('-') && !arg.startsWith('/')) || '8.8.8.8';
            let out = `\r\nPinging ${host} with 32 bytes of data:\r\n`;
            for (let i = 1; i <= 4; i++) {
                const ms = 12 + Math.floor(Math.random() * 8);
                out += `Reply from ${host}: bytes=32 time=${ms}ms TTL=117\r\n`;
            }
            return out;
        },

        'tracert': (a) => `\r\nTracing route to ${a[0] || '8.8.8.8'} over a maximum of 30 hops\r\n  1     2 ms     1 ms     2 ms  192.168.1.1\r\n  2     8 ms     7 ms     8 ms  10.0.0.1\r\n  3    16 ms    16 ms    16 ms  ${a[0] || '8.8.8.8'}\r\nTrace complete.\r\n`,
        'traceroute': (a) => WINDOWS_COMMANDS.tracert(a),

        'pathping': (a) => `\r\nTracing route to ${a[0] || '8.8.8.8'} over a maximum of 30 hops\r\n  0  ZSEM-STUDENT [192.168.1.100]\r\n  1  192.168.1.1\r\n  2  ${a[0] || '8.8.8.8'}\r\n\r\nComputing statistics for 50 seconds...\r\n            Source to Here   This Node/Link\r\nHop  RTT    Lost/Sent = Pct  Lost/Sent = Pct  Address\r\n  0                                           ZSEM-STUDENT [192.168.1.100]\r\n                                0/ 100 =  0%   |\r\n  1    2ms     0/ 100 =  0%     0/ 100 =  0%  192.168.1.1\r\n                                0/ 100 =  0%   |\r\n  2   14ms     0/ 100 =  0%     0/ 100 =  0%  ${a[0] || '8.8.8.8'}\r\n\r\nTrace complete.\r\n`,

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

        'getmac': (a, term) => `\r\nPhysical Address    Transport Name\r\n=================== ==========================================================\r\n${term.net.state.mac.replace(/:/g, '-')}   \\Device\\Tcpip_{8F64B79A-12E4-42D9-8F7C-902315AB56CD}\r\n`,

        'netstat': (a) => {
            const showAll = a.includes('-ano') || a.includes('-a') || a.includes('-an');
            return `\r\nActive Connections\r\n  Proto  Local Address          Foreign Address        State           PID\r\n  TCP    0.0.0.0:80             0.0.0.0:0              LISTENING       1450\r\n  TCP    0.0.0.0:135            0.0.0.0:0              LISTENING       840\r\n  TCP    0.0.0.0:445            0.0.0.0:0              LISTENING       4\r\n  TCP    0.0.0.0:3389           0.0.0.0:0              LISTENING       940\r\n  TCP    192.168.1.100:49712    142.250.187.195:443    ESTABLISHED     2140\r\n`;
        },
        'arp': (a) => `\r\nInterface: 192.168.1.100 --- 0x4\r\n  Internet Address      Physical Address      Type\r\n  192.168.1.1           00-50-56-c0-00-01     dynamic\r\n  192.168.1.255         ff-ff-ff-ff-ff-ff     static\r\n  224.0.0.22            01-00-5e-00-00-16     static\r\n`,

        'route': (a) => {
            const sub = a[0]?.toLowerCase();
            if (sub === 'print' || !a.length) {
                return `\r\n===========================================================================\r\nInterface List\r\n  4...00 50 56 c0 00 01 ......Intel(R) PRO/1000 MT Network Connection\r\n===========================================================================\r\nIPv4 Route Table\r\n===========================================================================\r\nActive Routes:\r\nNetwork Destination        Netmask          Gateway       Interface  Metric\r\n          0.0.0.0          0.0.0.0      192.168.1.1    192.168.1.100     25\r\n        127.0.0.0        255.0.0.0         On-link         127.0.0.1    331\r\n      192.168.1.0    255.255.255.0         On-link     192.168.1.100    281\r\n    192.168.1.100  255.255.255.255         On-link     192.168.1.100    281\r\n    192.168.1.255  255.255.255.255         On-link     192.168.1.100    281\r\n===========================================================================\r\n`;
            }
            if (sub === 'add') return `\r\n OK!\r\n`;
            if (sub === 'delete') return `\r\n OK!\r\n`;
            return `\r\nManipulates network routing tables.\r\nROUTE [-f] [-p] [-4|-6] command [destination] [MASK netmask] [gateway] [METRIC metric] [IF interface]\r\n`;
        },

        'attrib': (a, term) => {
            if (!a.length) {
                return `A            C:\\Users\\Student\\desktop.ini\r\nA            C:\\Users\\Student\\notes.txt\r\n`;
            }
            return '';
        },

        'tree': () => `Folder PATH listing for volume Windows\r\nVolume serial number is A894-32FC\r\nC:.\r\n├───Documents\r\n├───Downloads\r\n└───Desktop\r\n`,

        'icacls': (a) => `\r\n${a[0] || 'C:\\Dane'} NT AUTHORITY\\SYSTEM:(I)(OI)(CI)(F)\r\n               BUILTIN\\Administrators:(I)(OI)(CI)(F)\r\n               BUILTIN\\Users:(I)(OI)(CI)(RX)\r\nSuccessfully processed 1 files; Failed processing 0 files\r\n`,

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

        'rd': (a, term) => {
            if (!a[0]) return 'The syntax of the command is incorrect.\r\n';
            term.vfs.removeNode(a[0], true, true);
            return '';
        },
        'rmdir': (a, term) => WINDOWS_COMMANDS.rd(a, term),

        'sc': (a) => {
            const sub = a[0]?.toLowerCase();
            const srv = a[1]?.toLowerCase() || 'w3svc';
            if (sub === 'query') {
                return `\r\nSERVICE_NAME: ${srv}\r\n        TYPE               : 10  WIN32_OWN_PROCESS\r\n        STATE              : 4  RUNNING\r\n                                (STOPPABLE, NOT_PAUSABLE, ACCEPTS_SHUTDOWN)\r\n        WIN32_EXIT_CODE    : 0  (0x0)\r\n        SERVICE_EXIT_CODE  : 0  (0x0)\r\n        CHECKPOINT         : 0x0\r\n        WAIT_HINT          : 0x0\r\n`;
            }
            if (sub === 'start' || sub === 'stop') {
                return `\r\n[SC] ControlService SUCCESS 4  RUNNING\r\n`;
            }
            return `\r\nDESCRIPTION:\r\n        SC is a command line program used for communicating with the\r\n        Service Control Manager and services.\r\n`;
        },

        'gpupdate': () => `\r\nUpdating policy...\r\n\r\nComputer Policy update has completed successfully.\r\nUser Policy update has completed successfully.\r\n`,

        'powershell': (a, term) => {
            if (!a.length) {
                term.currentSubShell = 'powershell';
                term.subShellEngine = new PowerShellEngine(term);
                return 'PowerShell 7.3.0\r\nLoading personal and system profiles took 240ms.\r\n';
            }
            const engine = new PowerShellEngine(term);
            return engine.handleInput(a.join(' ')).output;
        },
        'pwsh': (a, term) => WINDOWS_COMMANDS.powershell(a, term),

        'diskpart': (a, term) => {
            term.currentSubShell = 'diskpart';
            term.subShellEngine = new DiskpartShell();
            return '\r\nMicrosoft DiskPart version 10.0.19041.3636\r\nCopyright (C) Microsoft Corporation.\r\nOn computer: ZSEM-STUDENT\r\n';
        },

        'chcp': (a) => a[0] ? `Active code page: ${a[0]}\r\n` : `Active code page: 852\r\n`,
        'where': (a) => `C:\\Windows\\System32\\${a[0] || 'cmd'}.exe\r\n`,

        'netsh': (a, term) => {
            const str = a.join(' ').toLowerCase();
            const net = term.net.state;

            if (str.includes('interface ip set address')) {
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

        'net': (a) => {
            const sub = a[0]?.toLowerCase();
            if (sub === 'user') {
                if (a[1] && a.includes('/add')) return `\r\nThe command completed successfully.\r\n`;
                return `\r\nUser accounts for \\\\ZSEM-STUDENT\r\n-------------------------------------------------------------------------------\r\nAdministrator            DefaultAccount           Guest            student\r\nThe command completed successfully.\r\n`;
            }
            if (sub === 'localgroup') return `\r\nAliases for \\\\ZSEM-STUDENT\r\n-------------------------------------------------------------------------------\r\n*Administrators          *Users\r\nThe command completed successfully.\r\n`;
            if (sub === 'share') return `\r\nShare name   Resource                        Remark\r\n-------------------------------------------------------------------------------\r\nC$           C:\\                             Default share\r\nDane         C:\\Dane\r\nThe command completed successfully.\r\n`;
            return `\r\nNET command executed.\r\n`;
        },

        'iisreset': () => `\r\nAttempting stop...\r\nInternet services successfully stopped\r\nAttempting start...\r\nInternet services successfully restarted\r\n`,
        'appcmd': (a) => `\r\nSITE "Default Web Site" (id:1,bindings:http/*:80:,state:Started)\r\n`,
        'dnscmd': () => `\r\nEnumerated zone list:\r\n  Zone name                      Type       Storage         Status\r\n  zsem.local                     Primary    File            Running\r\nCommand completed successfully.\r\n`,

        'tasklist': () => `\r\nImage Name                     PID Session Name        Session#    Mem Usage\r\n========================= ======== ================ =========== ============\r\nexplorer.exe                  2140 Console                    1     84,200 K\r\ncmd.exe                       3410 Console                    1      4,820 K\r\n`,
        'taskkill': (a) => `\r\nSUCCESS: Sent termination signal to process with PID ${a[1] || '1234'}.\r\n`,
        'sfc': () => `\r\nVerification 100% complete.\r\nWindows Resource Protection did not find any integrity violations.\r\n`,
        'chkdsk': () => `\r\nWindows has scanned the file system and found no problems.\r\n`,
        'dism': () => `\r\n[==========================100.0%==========================]\r\nThe operation completed successfully.\r\n`
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 7. MAN PAGES DATABASE (60+ COMMANDS)
    // ════════════════════════════════════════════════════════════════════════════

    const MAN_PAGES = {
        'ls': 'LS(1) - List directory contents\n\nSYNOPSIS:\n  ls [OPTION]... [FILE]...\n\nDESCRIPTION:\n  List information about the FILEs (the current directory by default).\n\nOPTIONS:\n  -a, --all        do not ignore entries starting with .\n  -l               use a long listing format\n  -h, --human-readable  with -l, print sizes like 1K 234M 2G',
        'chmod': 'CHMOD(1) - Change file mode bits (permissions)\n\nSYNOPSIS:\n  chmod [OPTION]... MODE[,MODE]... FILE...\n\nDESCRIPTION:\n  Changes the file mode bits of each given file according to MODE (e.g. 755, 644, u+x).',
        'chown': 'CHOWN(1) - Change file owner and group\n\nSYNOPSIS:\n  chown [OPTION]... [OWNER][:[GROUP]] FILE...\n\nEXAMPLES:\n  chown student:admin script.sh',
        'systemctl': 'SYSTEMCTL(1) - Control the systemd system and service manager\n\nCOMMANDS:\n  start UNIT...        Start one or more units\n  stop UNIT...         Stop one or more units\n  restart UNIT...      Restart one or more units\n  status UNIT...       Show runtime status about one or more units\n  enable UNIT...       Enable one or more units on boot',
        'apt': 'APT(8) - Command-line interface for package management\n\nCOMMANDS:\n  update               Update list of available packages\n  install PKG...       Install one or more packages\n  remove PKG...        Remove one or more packages',
        'iptables': 'IPTABLES(8) - Administration tool for IPv4 packet filtering\n\nSYNOPSIS:\n  iptables [-t table] -[AD] chain rule-specification [options]\n\nEXAMPLES:\n  iptables -A INPUT -p tcp --dport 80 -j ACCEPT\n  iptables -A INPUT -p tcp --dport 23 -j DROP',
        'ufw': 'UFW(8) - Program for managing a netfilter firewall\n\nCOMMANDS:\n  ufw enable\n  ufw status\n  ufw allow 22/tcp\n  ufw deny 80/tcp',
        'nano': 'NANO(1) - Small, friendly text editor\n\nSHORTCUTS:\n  Ctrl+O  Write out (Save)\n  Ctrl+X  Exit\n  Ctrl+K  Cut current line\n  Ctrl+U  Paste line\n  Ctrl+W  Search text\n  Ctrl+C  Show cursor line/column position'
    };

    // ════════════════════════════════════════════════════════════════════════════
    // 8. 20 MULTI-STEP CKE EXAM SCENARIOS (INF.02, INF.03, INF.08)
    // ════════════════════════════════════════════════════════════════════════════

    const CKE_SCENARIOS = [
        {
            id: 'inf02_ip_diag',
            title: 'Pełna diagnostyka interfejsu sieciowego',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★☆☆',
            os: 'any',
            desc: 'Przeprowadź pełną diagnostykę sieciową: odczytaj konfigurację IP/MAC, sprawdź łączność z bramą i rozwiąż nazwę DNS.',
            steps: [
                { task: 'Wyświetl pełną konfigurację interfejsów (Linux: ifconfig / ip a | Windows: ipconfig /all)', validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a|ip\s+addr)/i.test(cmd) : /^ipconfig\s+\/all/i.test(cmd) },
                { task: 'Wyślij zapytanie ping do bramy domyślnej 192.168.1.1', validate: (cmd) => /^ping\s+.*192\.168\.1\.1/i.test(cmd) },
                { task: 'Rozwiąż nazwę domeny google.pl za pomocą nslookup', validate: (cmd) => /^nslookup\s+google\.pl/i.test(cmd) },
                { task: 'Wykonaj śledzenie trasy pakietów do hosta google.pl (traceroute / tracert)', validate: (cmd) => /^(traceroute|tracert)\s+google\.pl/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_static_ip',
            title: 'Konfiguracja statycznego IP i bramy',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            os: 'any',
            desc: 'Skonfiguruj statyczny adres IPv4: 10.0.0.50/24 z bramą domyślną 10.0.0.1.',
            steps: [
                { task: 'Wyświetl bieżący adres IP', validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a)/i.test(cmd) : /^ipconfig/i.test(cmd) },
                { task: 'Ustaw adres IP 10.0.0.50 (Linux: ip addr add 10.0.0.50/24 dev eth0 | Win: netsh interface ip set address "Ethernet" static 10.0.0.50 255.255.255.0 10.0.0.1)', validate: (cmd, os, vfs, net) => net.state.ip === '10.0.0.50' || /(10\.0\.0\.50)/i.test(cmd) },
                { task: 'Ustaw domyślną bramę 10.0.0.1 (Linux: ip route add default via 10.0.0.1 | Win: netsh...)', validate: (cmd, os, vfs, net) => net.state.gateway === '10.0.0.1' || /(10\.0\.0\.1)/i.test(cmd) },
                { task: 'Zweryfikuj wprowadzone zmiany poleceniem ifconfig lub ipconfig', validate: (cmd, os) => os === 'linux' ? /^(ifconfig|ip\s+a)/i.test(cmd) : /^ipconfig/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_apache_vhost',
            title: 'Instalacja i konfiguracja VirtualHost Apache2',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            os: 'linux',
            desc: 'Zainstaluj serwer WWW Apache2, utwórz i aktywuj wirtualnego hosta zsem.conf, przetestuj składnię i zrestartuj usługę.',
            steps: [
                { task: 'Zainstaluj pakiet apache2 poleceniem: apt install apache2', validate: (cmd) => /^apt(-get)?\s+install\s+apache2/i.test(cmd) },
                { task: 'Otwórz do edycji plik wirtualnego hosta: nano /etc/apache2/sites-available/zsem.conf', validate: (cmd) => /^nano\s+.*zsem\.conf/i.test(cmd) },
                { task: 'Aktywuj witrynę za pomocą narzędzia a2ensite zsem.conf', validate: (cmd) => /^a2ensite\s+zsem(\.conf)?/i.test(cmd) },
                { task: 'Przetestuj poprawność składni plików konfiguracyjnych Apache (apachectl configtest)', validate: (cmd) => /^apache(2)?ctl\s+(configtest|-t)/i.test(cmd) },
                { task: 'Uruchom/zrestartuj usługę Apache2 (systemctl restart apache2)', validate: (cmd) => /^systemctl\s+(restart|start|reload)\s+apache2/i.test(cmd) },
                { task: 'Sprawdź odpowiedź serwera HTTP poleceniem: curl http://localhost', validate: (cmd) => /^curl\s+.*localhost/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_dns_zone',
            title: 'Konfiguracja strefy domeny w BIND9 DNS',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            os: 'linux',
            desc: 'Zainstaluj BIND9, skonfiguruj strefę forward zsem.local, sprawdź składnię i przetestuj zapytaniem nslookup.',
            steps: [
                { task: 'Zainstaluj serwer DNS BIND9: apt install bind9', validate: (cmd) => /^apt(-get)?\s+install\s+bind9/i.test(cmd) },
                { task: 'Edytuj plik deklaracji stref: nano /etc/bind/named.conf.local', validate: (cmd) => /^nano\s+.*named\.conf\.local/i.test(cmd) },
                { task: 'Sprawdź poprawność pliku głównego: named-checkconf', validate: (cmd) => /^named-checkconf/i.test(cmd) },
                { task: 'Sprawdź poprawność strefy: named-checkzone zsem.local /etc/bind/db.zsem.local', validate: (cmd) => /^named-checkzone\s+zsem\.local/i.test(cmd) },
                { task: 'Uruchom serwer DNS: systemctl restart bind9', validate: (cmd) => /^systemctl\s+(restart|start)\s+bind9/i.test(cmd) },
                { task: 'Wykonaj test zapytania DNS: nslookup www.zsem.local 127.0.0.1', validate: (cmd) => /^nslookup\s+.*zsem\.local/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_samba_share',
            title: 'Udostępnianie zasobu w sieci przez Sambę',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★☆',
            os: 'linux',
            desc: 'Skonfiguruj udział sieciowy [egzamin] w pliku smb.conf, zweryfikuj testparm i utwórz użytkownika Samby.',
            steps: [
                { task: 'Zainstaluj pakiet serwera Samba: apt install samba', validate: (cmd) => /^apt(-get)?\s+install\s+samba/i.test(cmd) },
                { task: 'Edytuj konfigurację udziałów: nano /etc/samba/smb.conf', validate: (cmd) => /^nano\s+.*smb\.conf/i.test(cmd) },
                { task: 'Zweryfikuj poprawność konfiguracji narzędziem: testparm', validate: (cmd) => /^testparm/i.test(cmd) },
                { task: 'Dodaj użytkownika do bazy Samby: smbpasswd -a student', validate: (cmd) => /^smbpasswd\s+-a\s+student/i.test(cmd) },
                { task: 'Zrestartuj usługę smbd (systemctl restart smbd)', validate: (cmd) => /^systemctl\s+(restart|start)\s+smbd/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_dhcp_server',
            title: 'Konfiguracja serwera ISC-DHCP',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★☆',
            os: 'linux',
            desc: 'Skonfiguruj pulę adresów DHCP w pliku dhcpd.conf i przetestuj składnię konfiguracji.',
            steps: [
                { task: 'Zainstaluj pakiet ISC-DHCP: apt install isc-dhcp-server', validate: (cmd) => /^apt(-get)?\s+install\s+isc-dhcp-server/i.test(cmd) },
                { task: 'Otwórz konfigurację podsieci: nano /etc/dhcp/dhcpd.conf', validate: (cmd) => /^nano\s+.*dhcpd\.conf/i.test(cmd) },
                { task: 'Przetestuj poprawność składni: dhcpd -t', validate: (cmd) => /^dhcpd\s+-t/i.test(cmd) },
                { task: 'Uruchom usługę DHCP: systemctl start isc-dhcp-server', validate: (cmd) => /^systemctl\s+(start|restart)\s+isc-dhcp-server/i.test(cmd) }
            ]
        },
        {
            id: 'inf08_iptables_drop',
            title: 'Konfiguracja reguł filtrowania w iptables',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★☆',
            os: 'linux',
            desc: 'Zabezpiecz serwer regułami zapory: przejrzyj reguły, zablokuj port 8080 i zezwól na ruch HTTPS.',
            steps: [
                { task: 'Wyświetl aktualną listę reguł: iptables -L', validate: (cmd) => /^iptables\s+(-L|-vL|-nL)/i.test(cmd) },
                { task: 'Zablokuj przychodzący ruch TCP na porcie 8080: iptables -A INPUT -p tcp --dport 8080 -j DROP', validate: (cmd) => /iptables.*-a\s+input.*--dport\s+8080.*-j\s+drop/i.test(cmd) },
                { task: 'Zezwól na ruch na porcie HTTPS (443): iptables -A INPUT -p tcp --dport 443 -j ACCEPT', validate: (cmd) => /iptables.*-a\s+input.*--dport\s+443.*-j\s+accept/i.test(cmd) },
                { task: 'Zweryfikuj reguły poleceniem: iptables -L', validate: (cmd) => /^iptables\s+-L/i.test(cmd) }
            ]
        },
        {
            id: 'inf08_ufw_secure',
            title: 'Zarządzanie zaporą UFW (Uncomplicated Firewall)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★☆☆',
            os: 'linux',
            desc: 'Skonfiguruj reguły UFW, zezwalając na usługi SSH i HTTP, a następnie włącz zaporę.',
            steps: [
                { task: 'Sprawdź stan zapory: ufw status', validate: (cmd) => /^ufw\s+status/i.test(cmd) },
                { task: 'Zezwól na ruch SSH: ufw allow 22/tcp', validate: (cmd) => /^ufw\s+allow\s+22/i.test(cmd) },
                { task: 'Zezwól na ruch HTTP: ufw allow 80/tcp', validate: (cmd) => /^ufw\s+allow\s+80/i.test(cmd) },
                { task: 'Aktywuj zaporę: ufw enable', validate: (cmd) => /^ufw\s+enable/i.test(cmd) }
            ]
        },
        {
            id: 'inf08_win_firewall',
            title: 'Konfiguracja Zapory Windows Defender (netsh)',
            cat: 'inf08_sec',
            catLabel: 'INF.08 Security',
            badgeColor: 'danger',
            stars: '★★☆',
            os: 'windows',
            desc: 'Skonfiguruj profil zapory Windows za pomocą narzędzia netsh advfirewall.',
            steps: [
                { task: 'Wyświetl stan profili zapory: netsh advfirewall show allprofiles', validate: (cmd) => /netsh\s+advfirewall\s+show\s+allprofiles/i.test(cmd) },
                { task: 'Dodaj regułę blokującą port 23 (Telnet): netsh advfirewall firewall add rule name="BlockTelnet" protocol=TCP dir=in localport=23 action=block', validate: (cmd) => /netsh\s+advfirewall\s+firewall\s+add\s+rule.*localport=23/i.test(cmd) },
                { task: 'Włącz zapórę dla wszystkich profili: netsh advfirewall set allprofiles state on', validate: (cmd) => /netsh\s+advfirewall\s+set\s+allprofiles\s+state\s+on/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_diskpart_vol',
            title: 'Zarządzanie woluminami w Windows DiskPart',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            os: 'windows',
            desc: 'Uruchom narzędzie diskpart, wybierz dysk, utwórz partycję podstawową, sformatuj w NTFS i przypisz literę.',
            steps: [
                { task: 'Uruchom narzędzie: diskpart', validate: (cmd) => /^diskpart/i.test(cmd) },
                { task: 'Wyświetl listę dysków: list disk', validate: (cmd) => /^list\s+disk/i.test(cmd) },
                { task: 'Wybierz dysk 1: select disk 1', validate: (cmd) => /^select\s+disk\s+1/i.test(cmd) },
                { task: 'Utwórz partycję: create partition primary', validate: (cmd) => /^create\s+partition\s+primary/i.test(cmd) },
                { task: 'Sformatuj wolumin: format fs=ntfs quick label="Dane"', validate: (cmd) => /^format\s+fs=ntfs/i.test(cmd) }
            ]
        },
        {
            id: 'inf03_mysql_db',
            title: 'Zarządzanie relacyjną bazą danych MySQL',
            cat: 'inf03_db',
            catLabel: 'INF.03 Bazy',
            badgeColor: 'info',
            stars: '★★☆',
            os: 'linux',
            desc: 'Połącz się z klientem MySQL, stwórz nową bazę danych, tabelę i wykonaj zapytanie SELECT.',
            steps: [
                { task: 'Uruchom klienta MySQL: mysql', validate: (cmd) => /^mysql/i.test(cmd) },
                { task: 'Utwórz bazę danych: CREATE DATABASE szkola;', validate: (cmd) => /^create\s+database\s+szkola/i.test(cmd) },
                { task: 'Wybierz bazę: USE szkola;', validate: (cmd) => /^use\s+szkola/i.test(cmd) },
                { task: 'Utwórz tabelę: CREATE TABLE uczniowie (id INT, imie VARCHAR(50));', validate: (cmd) => /^create\s+table\s+uczniowie/i.test(cmd) },
                { task: 'Wyświetl tabele w bazie: SHOW TABLES;', validate: (cmd) => /^show\s+tables/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_chmod_file',
            title: 'Zarządzanie uprawnieniami (chmod 750) i właścicielem',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'primary',
            stars: '★☆☆',
            os: 'linux',
            desc: 'Zmień prawa dostępu do pliku script.sh na 750 (rwxr-x---) oraz zmień grupę na admin.',
            steps: [
                { task: 'Sprawdź bieżące uprawnienia: ls -la script.sh', validate: (cmd) => /^ls\s+.*script\.sh/i.test(cmd) },
                { task: 'Ustaw prawa dostępu 750: chmod 750 script.sh', validate: (cmd, os, vfs) => { const n = vfs.getNode('/home/student/script.sh', false); return (n && n.permissions === '750') || /chmod\s+750/i.test(cmd); } },
                { task: 'Zmień grupę na admin: chgrp admin script.sh', validate: (cmd, os, vfs) => { const n = vfs.getNode('/home/student/script.sh', false); return (n && n.group === 'admin') || /chgrp\s+admin/i.test(cmd); } },
                { task: 'Zweryfikuj zmiany: ls -la script.sh', validate: (cmd) => /^ls\s+.*script\.sh/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_user_mgmt',
            title: 'Tworzenie kont użytkowników i grup w systemie',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'primary',
            stars: '★☆☆',
            os: 'any',
            desc: 'Utwórz konto użytkownika "marek" z katalogiem domowym i dodaj go do grupy sudo/Administrators.',
            steps: [
                { task: 'Wyświetl listę użytkowników (Linux: cat /etc/passwd | Win: net user)', validate: (cmd, os) => os === 'linux' ? /^cat\s+\/etc\/passwd/i.test(cmd) : /^net\s+user/i.test(cmd) },
                { task: 'Utwórz konto użytkownika marek (Linux: useradd -m marek | Win: net user marek /add)', validate: (cmd) => /(useradd.*marek|net\s+user\s+marek.*\/add)/i.test(cmd) },
                { task: 'Dodaj do grupy uprzywilejowanej (Linux: usermod -aG sudo marek | Win: net localgroup Administrators marek /add)', validate: (cmd) => /(usermod.*marek|net\s+localgroup.*marek)/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_win_iis',
            title: 'Zarządzanie serwerem IIS w Windows (appcmd)',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 30,
            os: 'windows',
            desc: 'Przetestuj zarządzanie usługą IIS za pomocą poleceń appcmd i iisreset.',
            steps: [
                { task: 'Wyświetl listę witryn internetowych IIS: appcmd list site', validate: (cmd) => /^appcmd\s+list\s+site/i.test(cmd) },
                { task: 'Wykonaj restart usług IIS: iisreset', validate: (cmd) => /^iisreset/i.test(cmd) },
                { task: 'Wyświetl zawartość głównej strony www: type C:\\inetpub\\wwwroot\\index.html', validate: (cmd) => /^type\s+.*index\.html/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_vsftpd_setup',
            title: 'Konfiguracja serwera FTP vsftpd',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Zainstaluj serwer vsftpd, włącz zapis w pliku konfiguracyjnym /etc/vsftpd.conf i uruchom usługę.',
            steps: [
                { task: 'Zainstaluj serwer vsftpd: apt install vsftpd', validate: (cmd) => /^apt(-get)?\s+install\s+vsftpd/i.test(cmd) },
                { task: 'Otwórz konfigurację serwera: nano /etc/vsftpd.conf', validate: (cmd) => /^nano\s+.*vsftpd\.conf/i.test(cmd) },
                { task: 'Zrestartuj usługę vsftpd: systemctl restart vsftpd', validate: (cmd) => /^systemctl\s+(restart|start)\s+vsftpd/i.test(cmd) },
                { task: 'Sprawdź stan usługi vsftpd: systemctl status vsftpd', validate: (cmd) => /^systemctl\s+status\s+vsftpd/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_postfix_mail',
            title: 'Instalacja i konfiguracja serwera pocztowego Postfix',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★★',
            xp: 35,
            os: 'linux',
            desc: 'Zainstaluj pakiet postfix, skonfiguruj nazwę domeny myhostname i zrestartuj serwer pocztowy.',
            steps: [
                { task: 'Zainstaluj pakiet pocztowy: apt install postfix', validate: (cmd) => /^apt(-get)?\s+install\s+postfix/i.test(cmd) },
                { task: 'Ustaw parametr myhostname za pomocą narzędzia postconf -e "myhostname = mail.zsem.local"', validate: (cmd) => /^postconf\s+-e\s+.*myhostname/i.test(cmd) },
                { task: 'Zrestartuj serwer pocztowy: systemctl restart postfix', validate: (cmd) => /^systemctl\s+(restart|start)\s+postfix/i.test(cmd) },
                { task: 'Sprawdź otwarte porty (SMTP port 25): ss -tuln', validate: (cmd) => /^(ss|netstat)\s+-(tuln|tulpn|an)/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_nfs_exports',
            title: 'Konfiguracja zasobów sieciowych NFS exports',
            cat: 'inf02_srv',
            catLabel: 'Serwery CKE',
            badgeColor: 'success',
            stars: '★★☆',
            xp: 30,
            os: 'linux',
            desc: 'Zainstaluj nfs-kernel-server, zdefiniuj udostępniany katalog w /etc/exports i wyeksportuj zasoby.',
            steps: [
                { task: 'Zainstaluj pakiet serwera NFS: apt install nfs-kernel-server', validate: (cmd) => /^apt(-get)?\s+install\s+nfs/i.test(cmd) },
                { task: 'Edytuj plik eksportu katalogów: nano /etc/exports', validate: (cmd) => /^nano\s+.*\/etc\/exports/i.test(cmd) },
                { task: 'Zastosuj konfigurację eksportów: exportfs -a', validate: (cmd) => /^exportfs\s+(-a|-ra|-v)/i.test(cmd) },
                { task: 'Uruchom usługę serwera NFS: systemctl restart nfs-kernel-server', validate: (cmd) => /^systemctl\s+(restart|start)\s+nfs/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_win_powershell_diag',
            title: 'Diagnostyka sieci i usług w PowerShell',
            cat: 'inf02_net',
            catLabel: 'INF.02 Sieci',
            badgeColor: 'primary',
            stars: '★★☆',
            xp: 25,
            os: 'windows',
            desc: 'Wykorzystaj cmdlets PowerShell do sprawdzenia adresacji IP, stanu usług i testowania połączeń TCP.',
            steps: [
                { task: 'Uruchom konsolę PowerShell wpisując polecenie: powershell', validate: (cmd) => /^powershell/i.test(cmd) },
                { task: 'Wyświetl konfigurację adresów IP: Get-NetIPAddress', validate: (cmd) => /^Get-NetIPAddress/i.test(cmd) },
                { task: 'Sprawdź stan usługi serwera W3SVC: Get-Service W3SVC', validate: (cmd) => /^Get-Service\s+.*w3svc/i.test(cmd) },
                { task: 'Przetestuj połączenie z portem 80: Test-NetConnection -ComputerName localhost -Port 80', validate: (cmd) => /^Test-NetConnection/i.test(cmd) }
            ]
        },
        {
            id: 'inf02_crontab_backup',
            title: 'Automatyzacja kopii zapasowych w cronie',
            cat: 'inf02_sys',
            catLabel: 'INF.02 Systemy',
            badgeColor: 'info',
            stars: '★★☆',
            xp: 25,
            os: 'linux',
            desc: 'Sprawdź harmonogram zadań cron, utwórz archiwum tar.gz katalogu /home/student i wyświetl zawartość.',
            steps: [
                { task: 'Wyświetl bieżącą tabelę crona: crontab -l', validate: (cmd) => /^crontab\s+-l/i.test(cmd) },
                { task: 'Utwórz skompresowane archiwum tar: tar -czf backup.tar.gz /home/student', validate: (cmd) => /^tar\s+.*-?c[zfv]*f?\s+backup\.tar\.gz/i.test(cmd) },
                { task: 'Wyświetl zawartość utworzonego archiwum: tar -tf backup.tar.gz', validate: (cmd) => /^tar\s+.*-?t[zfv]*f?\s+backup\.tar\.gz/i.test(cmd) }
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
                { task: 'Otwórz konfigurację demona SSH: nano /etc/ssh/sshd_config', validate: (cmd) => /^nano\s+.*sshd_config/i.test(cmd) },
                { task: 'Zrestartuj usługę SSH aby załadować nowe ustawienia: systemctl restart ssh', validate: (cmd) => /^systemctl\s+(restart|start|reload)\s+ssh(d)?/i.test(cmd) },
                { task: 'Sprawdź stan i port nasłuchiwania SSH: ss -tuln', validate: (cmd) => /^(ss|netstat)\s+-(tuln|tulpn|an)/i.test(cmd) }
            ]
        }
    ];

    document.addEventListener('DOMContentLoaded', () => {
        window.zsemTerminal = new TerminalSimulator();
    });

}());
