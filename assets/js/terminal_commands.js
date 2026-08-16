/**
 * Terminal Commands Engine
 *
 * Handles all command simulation for the CLI Lab.
 * Supports Linux (Bash) and Windows (CMD/PowerShell) modes.
 */
(function () {
    'use strict';

    // ── Simulation Helpers ────────────────────────────────────────────────────

    function simulatePing(args, isWindows) {
        const host = args[0] || '8.8.8.8';
        const count = isWindows ? 4 : 4;
        let out = isWindows
            ? `\r\nPinging ${host} with 32 bytes of data:`
            : `PING ${host} (${host}): 56 data bytes`;
        for (let i = 1; i <= count; i++) {
            const ms = 12 + Math.floor(Math.random() * 24);
            out += isWindows
                ? `\r\nReply from ${host}: bytes=32 time=${ms}ms TTL=117`
                : `\n64 bytes from ${host}: icmp_seq=${i} ttl=117 time=${ms}.${Math.floor(Math.random()*9)} ms`;
        }
        const avg = 18 + Math.floor(Math.random() * 10);
        out += isWindows
            ? `\r\n\r\nPing statistics for ${host}:\r\n    Packets: Sent = 4, Received = 4, Lost = 0 (0% loss),\r\nApproximate round trip times in milli-seconds:\r\n    Minimum = 12ms, Maximum = 36ms, Average = ${avg}ms`
            : `\n--- ${host} ping statistics ---\n4 packets transmitted, 4 received, 0% packet loss\nrtt min/avg/max = 12/${avg}/36 ms`;
        return out;
    }

    function simulateTraceroute(args, isWindows) {
        const host = args[0] || '8.8.8.8';
        const cmd  = isWindows ? 'tracert' : 'traceroute';
        let out = isWindows
            ? `\r\nTracing route to ${host} over a maximum of 30 hops:`
            : `traceroute to ${host} (${host}), 30 hops max, 60 byte packets`;
        const hops = [
            ['192.168.1.1', 'brama.local'],
            ['10.0.0.1', 'isp-gw.pl'],
            ['213.180.1.1', 'transit.pl'],
            ['216.58.215.1', 'google-net.pl'],
            [host, host],
        ];
        hops.forEach((h, i) => {
            const ms = 2 + i * 8 + Math.floor(Math.random() * 5);
            out += isWindows
                ? `\r\n  ${i+1}    ${ms} ms    ${ms+1} ms    ${ms} ms  ${h[0]} [${h[1]}]`
                : `\n ${i+1}  ${h[0]} (${h[1]})  ${ms}.${Math.floor(Math.random()*9)} ms`;
        });
        out += isWindows ? '\r\n\r\nTrace complete.' : '\n';
        return out;
    }

    function simulateNslookup(args) {
        const host = args[0] || 'google.pl';
        return `Server:\t\t8.8.8.8\nAddress:\t8.8.8.8#53\n\nNon-authoritative answer:\nName:\t${host}\nAddress: 142.250.${Math.floor(Math.random()*200)+1}.${Math.floor(Math.random()*200)+1}`;
    }

    function generateNetstat(isWindows) {
        const rows = [
            ['tcp','0','0','0.0.0.0:22','0.0.0.0:*','LISTEN'],
            ['tcp','0','0','0.0.0.0:80','0.0.0.0:*','LISTEN'],
            ['tcp','0','0','127.0.0.1:3306','0.0.0.0:*','LISTEN'],
            ['tcp','0','120','192.168.1.100:43210','8.8.8.8:443','ESTABLISHED'],
        ];
        if (isWindows) {
            let out = '\r\nActive Connections\r\n\r\n  Proto  Local Address          Foreign Address        State\r\n';
            out += rows.map(r => `  TCP    0.0.0.0:${r[5]==='LISTEN'?r[3].split(':')[1]:'443'}         0.0.0.0:0              ${r[5]}`).join('\r\n');
            return out;
        }
        let out = 'Active Internet connections (only servers)\nProto Recv-Q Send-Q Local Address           Foreign Address         State\n';
        out += rows.map(r => `${r[0].padEnd(6)}${r[1].padEnd(7)}${r[2].padEnd(7)}${r[3].padEnd(23)}${r[4].padEnd(23)}${r[5]}`).join('\n');
        return out;
    }

    function generateIpconfig() {
        return `\r\nWindows IP Configuration\r\n\r\nEthernet adapter Ethernet:\r\n\r\n   Connection-specific DNS Suffix  . : zsem.local\r\n   IPv4 Address. . . . . . . . . . . : 192.168.1.100\r\n   Subnet Mask . . . . . . . . . . . : 255.255.255.0\r\n   Default Gateway . . . . . . . . . : 192.168.1.1`;
    }

    function generateIpconfigAll() {
        return generateIpconfig() + `\r\n   Physical Address. . . . . . . . . : 00-0C-29-AB-CD-EF\r\n   DHCP Enabled. . . . . . . . . . . : Yes\r\n   DHCP Server . . . . . . . . . . . : 192.168.1.1\r\n   DNS Servers . . . . . . . . . . . : 8.8.8.8\r\n                                       8.8.4.4\r\n   Lease Obtained. . . . . . . . . . : ${new Date().toLocaleString('pl-PL')}\r\n   Lease Expires . . . . . . . . . . : ${new Date(Date.now()+86400000).toLocaleString('pl-PL')}`;
    }

    function generateRoutePrint() {
        return `\r\n===========================================================================\r\nInterface List\r\n  4...00 0c 29 ab cd ef ......Intel(R) PRO/1000 MT Network Connection\r\n===========================================================================\r\n\r\nIPv4 Route Table\r\n===========================================================================\r\nActive Routes:\r\nNetwork Destination        Netmask          Gateway       Interface  Metric\r\n          0.0.0.0          0.0.0.0      192.168.1.1    192.168.1.100     25\r\n        127.0.0.0        255.0.0.0         On-link         127.0.0.1    331\r\n      192.168.1.0    255.255.255.0         On-link     192.168.1.100    281\r\n===========================================================================`;
    }

    function generateSysteminfo() {
        return `\r\nHost Name:                 ZSEM-STUDENT\r\nOS Name:                   Microsoft Windows 10 Pro\r\nOS Version:                10.0.19045 N/A Build 19045\r\nSystem Type:               x64-based PC\r\nProcessor(s):              1 Processor(s) Installed. Intel(R) Core(TM) i5\r\nTotal Physical Memory:     8,192 MB\r\nAvailable Physical Memory: 3,456 MB\r\nPage File Space:           16,384 MB\r\nDomain:                    zsem.local`;
    }

    function generateIfconfig() {
        return `eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500\n        inet 192.168.1.100  netmask 255.255.255.0  broadcast 192.168.1.255\n        inet6 fe80::20c:29ff:feab:cdef  prefixlen 64  scopeid 0x20<link>\n        ether 00:0c:29:ab:cd:ef  txqueuelen 1000  (Ethernet)\n        RX packets 12834  bytes 11485902 (10.9 MiB)\n        TX packets 8342  bytes 1245678 (1.1 MiB)\n\nlo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536\n        inet 127.0.0.1  netmask 255.0.0.0\n        loop  txqueuelen 1000  (Local Loopback)`;
    }

    function generateIpAddr() {
        return `1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 qdisc noqueue state UNKNOWN group default qlen 1000\n    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00\n    inet 127.0.0.1/8 scope host lo\n2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc pfifo_fast state UP group default qlen 1000\n    link/ether 00:0c:29:ab:cd:ef brd ff:ff:ff:ff:ff:ff\n    inet 192.168.1.100/24 brd 192.168.1.255 scope global dynamic eth0\n    inet6 fe80::20c:29ff:feab:cdef/64 scope link`;
    }

    function generateDf() {
        return `Filesystem      Size  Used Avail Use% Mounted on\ntmpfs           795M  2.4M  793M   1% /run\n/dev/sda1        30G  8.4G   20G  30% /\ntmpfs           3.9G     0  3.9G   0% /dev/shm\n/dev/sda2       512M   53M  459M  11% /boot`;
    }

    function handleSystemctl(args) {
        const sub  = args[0] || 'status';
        const svc  = args[1] || 'nginx';
        if (sub === 'status') {
            return `● ${svc}.service - ${svc.charAt(0).toUpperCase()+svc.slice(1)} HTTP Server\n     Loaded: loaded (/lib/systemd/system/${svc}.service; enabled)\n     Active: active (running) since ${new Date().toISOString().slice(0,16)} ago\n   Main PID: 1234 (${svc})\n      Tasks: 2 (limit: 4915)\n     Memory: 6.8M`;
        }
        if (sub === 'start' || sub === 'stop' || sub === 'restart') {
            return `Executing: /lib/systemd/systemd-sysv-install ${sub} ${svc}\n[  OK  ] ${sub.charAt(0).toUpperCase()+sub.slice(1)}ed ${svc}.service`;
        }
        return `systemctl: unknown subcommand '${sub}'`;
    }

    function handleIptables(args) {
        if (!args.length) return 'Usage: iptables [-L] [-A chain] [-D chain] [-F] [options]';
        const joined = args.join(' ');
        if (args.includes('-L') || args.includes('--list')) {
            return `Chain INPUT (policy ACCEPT)\ntarget     prot opt source               destination\nDROP       tcp  --  0.0.0.0/0            0.0.0.0/0            tcp dpt:8080\n\nChain FORWARD (policy DROP)\n\nChain OUTPUT (policy ACCEPT)`;
        }
        if (args.includes('-A')) {
            return `# Rule added to ${args[args.indexOf('-A')+1] || 'INPUT'} chain`;
        }
        if (args.includes('-F')) { return '# All rules flushed.'; }
        return `iptables: ${joined}`;
    }

    function handleNetsh(args) {
        const sub = (args[0] || '').toLowerCase();
        if (sub === 'interface' || sub === 'int') {
            return `\r\nAdmin State    State          Type             Interface Name\r\n-------------------------------------------------------------------------\r\nEnabled        Connected      Dedicated        Ethernet\r\nEnabled        Connected      Loopback         Loopback Pseudo-Interface 1`;
        }
        if (sub === 'advfirewall') {
            return '\r\nOk.\r\n';
        }
        return `\r\nThe following commands are available:\r\n\r\nCommands in this context:\r\nadvfirewall - Changes to the 'netsh advfirewall' context.\r\ninterface   - Changes to the 'netsh interface' context.\r\nwlan        - Changes to the 'netsh wlan' context.`;
    }

    function generateLs(args) {
        const files = ['anaconda3/', 'Desktop/', 'Documents/', 'Downloads/', '.bashrc', '.profile', 'script.sh', 'network.conf'];
        if (args.includes('-la') || args.includes('-al') || args.includes('-l')) {
            const now = new Date().toLocaleString('en-US',{month:'short',day:'2-digit',hour:'2-digit',minute:'2-digit'});
            return `total 64\ndrwxr-xr-x 1 student student 4096 ${now} .\ndrwxr-xr-x 1 root    root    4096 ${now} ..\n-rw-r--r-- 1 student student  220 ${now} .bashrc\n-rw-r--r-- 1 student student 3526 ${now} .profile\ndrwxr-xr-x 2 student student 4096 ${now} Desktop\ndrwxr-xr-x 2 student student 4096 ${now} Documents\ndrwxr-xr-x 2 student student 4096 ${now} Downloads\n-rwxr-xr-x 1 student student  512 ${now} script.sh`;
        }
        return files.join('  ');
    }

    // ── Command Registries ────────────────────────────────────────────────────

    const LINUX_COMMANDS = {
        'ifconfig':          () => generateIfconfig(),
        'ip':                (a) => a[0]==='a'||a[0]==='addr' ? generateIpAddr() : `ip: command '${a[0]}' not recognized. Try 'ip a' or 'ip addr'`,
        'ip a':              () => generateIpAddr(),
        'ip addr':           () => generateIpAddr(),
        'ping':              (a) => simulatePing(a, false),
        'traceroute':        (a) => simulateTraceroute(a, false),
        'nslookup':          (a) => simulateNslookup(a),
        'dig':               (a) => simulateNslookup(a) + '\n;; Query time: 12 msec\n;; SERVER: 8.8.8.8#53',
        'systemctl':         (a) => handleSystemctl(a),
        'service':           (a) => handleSystemctl([a[1]||'status', a[0]||'nginx']),
        'chmod':             (a) => a.length >= 2 ? `chmod: uprawnienia '${a[0]}' ustawione dla '${a[1]}'` : 'Usage: chmod <mode> <file>',
        'chown':             (a) => a.length >= 2 ? `chown: właściciel zmieniony na '${a[0]}' dla '${a[1]}'` : 'Usage: chown <owner> <file>',
        'iptables':          (a) => handleIptables(a),
        'netstat':           () => generateNetstat(false),
        'ss':                () => 'Netid  State   Recv-Q  Send-Q  Local Address:Port   Peer Address:Port\ntcp    LISTEN  0       128     0.0.0.0:22          0.0.0.0:*\ntcp    LISTEN  0       80      0.0.0.0:80          0.0.0.0:*',
        'df':                () => generateDf(),
        'df -h':             () => generateDf(),
        'cat':               (a) => {
            if (!a[0]) return 'cat: missing file operand';
            if (a[0]==='/etc/passwd') return 'root:x:0:0:root:/root:/bin/bash\ndaemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin\nstudent:x:1000:1000:Student,,,:/home/student:/bin/bash';
            if (a[0]==='/etc/hosts') return '127.0.0.1\tlocalhost\n127.0.1.1\tzsem-lab\n192.168.1.1\tbrama.local';
            return `cat: ${a[0]}: No such file or directory`;
        },
        'ls':                (a) => generateLs(a),
        'pwd':               () => '/home/student',
        'whoami':            () => 'student',
        'hostname':          () => 'zsem-lab',
        'uname':             (a) => a.includes('-a') ? 'Linux zsem-lab 5.15.0-89-generic #99-Ubuntu SMP x86_64 GNU/Linux' : 'Linux',
        'echo':              (a) => a.join(' '),
        'date':              () => new Date().toString(),
        'uptime':            () => `${new Date().toTimeString().slice(0,8)} up 2 days, 3:45, 1 user, load average: 0.12, 0.08, 0.05`,
        'ps':                () => 'PID   TTY      TIME CMD\n 1234 pts/0    00:00:01 bash\n 5678 pts/0    00:00:00 sshd\n 9012 pts/0    00:00:00 ps',
        'top':               () => '⚠️  top: interaktywne — symulacja nieobsługiwana. Użyj ps lub htop.',
        'man':               (a) => a[0] ? `Brak pełnej dokumentacji w trybie symulacji. Spróbuj: ${a[0]} --help` : 'Co chcesz sprawdzić? Użyj: man <polecenie>',
        'mkdir':             (a) => a[0] ? `Katalog '${a[0]}' utworzony.` : 'mkdir: brak nazwy katalogu',
        'rm':                (a) => a[0] ? `rm: usunięto '${a.join(' ')}'` : 'rm: brak operandu',
        'touch':             (a) => a[0] ? `Plik '${a[0]}' zaktualizowany.` : 'touch: brak operandu',
        'cp':                (a) => a.length>=2 ? `Skopiowano '${a[0]}' do '${a[1]}'` : 'cp: brak operandu',
        'mv':                (a) => a.length>=2 ? `Przeniesiono '${a[0]}' do '${a[1]}'` : 'mv: brak operandu',
        'grep':              (a) => a.length>=2 ? `grep: symulacja — szukam '${a[0]}' w '${a[1]}'...` : 'Usage: grep <pattern> <file>',
        'curl':              (a) => a[0] ? `Pobieranie ${a[0]}...\n  % Total    % Received % Xferd  Average Speed\n100  1234  100  1234    0     0   9876      0` : 'curl: try specifying a URL',
        'apt':               (a) => a[0]==='update'?'Hit:1 http://archive.ubuntu.com focal InRelease\nReading package lists... Done':'apt: try: apt update | apt install <package>',
        'sudo':              (a) => a.length ? `[sudo] hasło studenta: \n${LINUX_COMMANDS[a[0]] ? LINUX_COMMANDS[a[0]](a.slice(1)) : `${a[0]}: komenda wykonana z uprawnieniami root`}` : 'sudo: brak komendy',
        'ssh':               (a) => `ssh: łączenie z ${a[0] || 'host'}...\nWarning: Permanently added '${a[0]||'host'}' (ECDSA) to the list of known hosts.\n[Symulacja: połączenie SSH nieobsługiwane]`,
        'history':           () => '  1  ifconfig\n  2  ping 8.8.8.8\n  3  traceroute google.com\n  4  netstat\n  5  df -h',
        'clear':             () => '__CLEAR__',
        'exit':              () => '__EXIT__',
        'help':              () => 'Dostępne polecenia Linux:\nifconfig, ip a, ping, traceroute, nslookup, dig, systemctl, chmod, chown,\niptables, netstat, ss, df -h, cat, ls, pwd, whoami, hostname, uname,\nps, mkdir, rm, touch, cp, mv, grep, curl, apt, sudo, ssh, history, clear',
    };

    const WINDOWS_COMMANDS = {
        'ipconfig':          () => generateIpconfig(),
        'ipconfig /all':     () => generateIpconfigAll(),
        'ping':              (a) => simulatePing(a, true),
        'tracert':           (a) => simulateTraceroute(a, true),
        'nslookup':          (a) => simulateNslookup(a),
        'netstat':           () => generateNetstat(true),
        'netsh':             (a) => handleNetsh(a),
        'route':             (a) => a[0]==='print' ? generateRoutePrint() : '\r\nUsage: route [print|add|delete] ...',
        'route print':       () => generateRoutePrint(),
        'systeminfo':        () => generateSysteminfo(),
        'hostname':          () => 'ZSEM-STUDENT\r\n',
        'whoami':            () => 'zsem-student\\student\r\n',
        'echo':              (a) => a.join(' ') + '\r\n',
        'date /t':           () => new Date().toLocaleDateString('pl-PL') + '\r\n',
        'time /t':           () => new Date().toLocaleTimeString('pl-PL') + '\r\n',
        'dir':               () => '\r\nDirectory of C:\\Users\\Student\r\n\r\n08/16/2026  09:00 AM    <DIR>          .\r\n08/16/2026  09:00 AM    <DIR>          ..\r\n08/16/2026  09:00 AM    <DIR>          Desktop\r\n08/16/2026  09:00 AM    <DIR>          Documents\r\n08/16/2026  09:00 AM              512 script.bat\r\n               1 File(s)          512 bytes\r\n               4 Dir(s)  10,000,000,000 bytes free',
        'tasklist':          () => '\r\nImage Name                     PID Session Name   Mem Usage\r\n========================= ======== ========== ===========\r\nSystem Idle Process              0 Services          8 K\r\nSystem                           4 Services      7,432 K\r\nexplorer.exe                  3456 Console       56,320 K\r\nchrome.exe                    7890 Console      112,456 K',
        'net':               (a) => a[0]==='view'?'\r\nServer Name            Remark\r\n-------------------------------------------------------------------------------\r\n\\\\ZSEM-SERVER\r\n\\\\ZSEM-STUDENT':'\r\nUsage: net [user|view|share|start|stop] ...',
        'arp':               (a) => a.includes('-a')?'\r\nInterface: 192.168.1.100 --- 0x4\r\n  Internet Address      Physical Address      Type\r\n  192.168.1.1           00-0c-29-fe-dc-ba     dynamic\r\n  192.168.1.255         ff-ff-ff-ff-ff-ff     static':'arp -a',
        'sc':                (a) => '\r\nSERVICE_NAME: ' + (a[1]||'wuauserv') + '\r\n        TYPE               : 20  WIN32_SHARE_PROCESS\r\n        STATE              : 4  RUNNING',
        'cls':               () => '__CLEAR__',
        'exit':              () => '__EXIT__',
        'help':              () => 'Dostępne polecenia Windows:\r\nipconfig, ipconfig /all, ping, tracert, nslookup, netstat, netsh,\r\nroute print, systeminfo, hostname, whoami, dir, tasklist, net, arp, sc, cls',
    };

    // ── Scenarios ─────────────────────────────────────────────────────────────

    const SCENARIOS = [
        {
            id: 'check_ip',
            title: 'Sprawdź adres IP interfejsu',
            desc:  'Wyświetl konfigurację sieciową urządzenia i znajdź adres IPv4 interfejsu eth0/Ethernet.',
            os: 'any',
            steps: [
                {
                    task: 'Wpisz polecenie wyświetlające konfigurację sieci.',
                    validate: (cmd, os) => os==='linux' ? ['ifconfig','ip a','ip addr'].includes(cmd.split(' ')[0]) : cmd.toLowerCase().startsWith('ipconfig'),
                    hint: 'Linux: ifconfig lub ip a | Windows: ipconfig',
                }
            ],
        },
        {
            id: 'ping_gw',
            title: 'Testuj połączenie z bramą',
            desc:  'Sprawdź, czy możesz komunikować się z bramą domyślną 192.168.1.1 używając polecenia ping.',
            os: 'any',
            steps: [
                {
                    task: 'Wyślij żądanie ping do bramy 192.168.1.1.',
                    validate: (cmd) => /^ping\s+192\.168\.1\.1/.test(cmd.toLowerCase()),
                    hint: 'Użyj: ping 192.168.1.1',
                }
            ],
        },
        {
            id: 'open_ports',
            title: 'Sprawdź otwarte porty',
            desc:  'Wyświetl listę aktywnych połączeń sieciowych i portów nasłuchujących.',
            os: 'any',
            steps: [
                {
                    task: 'Wyświetl aktywne połączenia i porty nasłuchujące.',
                    validate: (cmd) => cmd.toLowerCase().startsWith('netstat'),
                    hint: 'Użyj: netstat lub netstat -an',
                }
            ],
        },
        {
            id: 'chmod_755',
            title: 'Zmień uprawnienia pliku (Linux)',
            desc:  'Zmień uprawnienia pliku script.sh na 755 (właściciel: rwx, grupa: r-x, inni: r-x).',
            os: 'linux',
            steps: [
                {
                    task: 'Ustaw uprawnienia 755 dla pliku script.sh.',
                    validate: (cmd) => /^chmod\s+755\s+script\.sh/.test(cmd.toLowerCase()),
                    hint: 'Użyj: chmod 755 script.sh',
                }
            ],
        },
        {
            id: 'firewall_block',
            title: 'Zablokuj port 8080 (Linux)',
            desc:  'Dodaj regułę firewalla blokującą przychodzący ruch TCP na porcie 8080.',
            os: 'linux',
            steps: [
                {
                    task: 'Dodaj regułę iptables blokującą port 8080 TCP na wejściu.',
                    validate: (cmd) => /iptables.*-a\s+input.*--dport\s+8080.*-j\s+drop/i.test(cmd),
                    hint: 'Użyj: iptables -A INPUT -p tcp --dport 8080 -j DROP',
                }
            ],
        },
    ];

    // ── Terminal Engine ────────────────────────────────────────────────────────

    let currentOs       = 'linux';
    let commandHistory  = [];
    let historyIndex    = -1;
    let activeScenario  = null;
    let activeStepIndex = 0;
    let completedScen   = new Set();

    const termOutput  = document.getElementById('termOutput');
    const termInput   = document.getElementById('termInput');
    const promptLabel = document.getElementById('termPromptLabel');
    const termTitle   = document.getElementById('termTitle');

    function print(text, cls = '') {
        if (!termOutput) return;
        const div = document.createElement('div');
        div.className = 'term-line' + (cls ? ' ' + cls : '');
        div.textContent = text;
        termOutput.appendChild(div);
        termOutput.scrollTop = termOutput.scrollHeight;
    }

    function printHtml(html) {
        if (!termOutput) return;
        const div = document.createElement('div');
        div.className = 'term-line';
        div.innerHTML = html;
        termOutput.appendChild(div);
        termOutput.scrollTop = termOutput.scrollHeight;
    }

    function clear() {
        if (termOutput) termOutput.innerHTML = '';
    }

    function boot() {
        clear();
        if (currentOs === 'linux') {
            print('┌─────────────────────────────────────────────────────┐', 'dim');
            print('│       ZSEM Tech CLI Lab — Linux Bash Emulator        │', 'dim');
            print('│  Wpisz: help  aby zobaczyć dostępne polecenia        │', 'dim');
            print('└─────────────────────────────────────────────────────┘', 'dim');
            print('');
            print('Last login: ' + new Date().toString().slice(0,24) + ' from 192.168.1.1', 'dim');
            print('student@zsem-lab:~$ ', 'success');
        } else {
            print('╔══════════════════════════════════════════════════════╗', 'dim');
            print('║   ZSEM Tech CLI Lab — Windows CMD/PowerShell Emulator ║', 'dim');
            print('╚══════════════════════════════════════════════════════╝', 'dim');
            print('');
            print('Microsoft Windows [Version 10.0.19045.3570]', 'white');
            print('(c) Microsoft Corporation. All rights reserved.', 'dim');
            print('');
            print('C:\\Users\\Student>', 'prompt');
        }
        updatePrompt();
    }

    function updatePrompt() {
        if (!promptLabel || !termTitle) return;
        if (currentOs === 'linux') {
            promptLabel.textContent = 'student@zsem-lab:~$';
            termTitle.textContent   = 'bash — student@zsem-lab: ~';
        } else {
            promptLabel.textContent = 'C:\\Users\\Student>';
            termTitle.textContent   = 'cmd.exe — C:\\Users\\Student';
        }
    }

    function executeCommand(rawInput) {
        const input = rawInput.trim();
        if (!input) return;

        // Echo the command
        const prompt = currentOs === 'linux' ? 'student@zsem-lab:~$ ' : 'C:\\Users\\Student>';
        print(prompt + input, 'prompt');

        commandHistory.unshift(input);
        if (commandHistory.length > 50) commandHistory.pop();
        historyIndex = -1;

        const parts = input.split(/\s+/);
        const cmd   = parts[0].toLowerCase();
        const args  = parts.slice(1);
        const fullCmd = input.toLowerCase();

        const registry = currentOs === 'linux' ? LINUX_COMMANDS : WINDOWS_COMMANDS;

        // Find command handler — try full string first, then base command
        let handler = registry[fullCmd] || registry[cmd];

        // Handle combined aliases like "ip a", "route print", "ipconfig /all"
        if (!handler && parts.length >= 2) {
            const twoWord = (parts[0]+' '+parts[1]).toLowerCase();
            handler = registry[twoWord];
        }

        if (handler) {
            const result = handler(args);
            if (result === '__CLEAR__') {
                clear();
                return;
            }
            if (result === '__EXIT__') {
                print('Sesja zakończona.', 'dim');
                return;
            }
            if (result) {
                result.split('\n').forEach(line => print(line, 'white'));
            }
        } else {
            print(`${cmd}: command not found. Try 'help'`, 'error');
        }

        print('');

        // Validate against active scenario
        checkScenarioValidation(input);
    }

    // ── Scenario Logic ────────────────────────────────────────────────────────

    function checkScenarioValidation(input) {
        if (!activeScenario) return;
        const steps = activeScenario.steps;
        if (activeStepIndex >= steps.length) return;

        const step = steps[activeStepIndex];
        if (step.validate(input, currentOs)) {
            print('✅  Poprawnie! Zadanie wykonane.', 'success');
            print('');
            activeStepIndex++;

            if (activeStepIndex >= steps.length) {
                print('🎉  Scenariusz ukończony!', 'success');
                completedScen.add(activeScenario.id);
                renderScenarioList();
                activeScenario  = null;
                activeStepIndex = 0;
                updateScenarioProgress();
                document.getElementById('scenarioProgressWrap').style.display = 'none';
                document.getElementById('activeScenarioDesc').innerHTML =
                    '<i class="bi bi-check-circle-fill text-success me-1"></i>Scenariusz ukończony! Wybierz następny.';
                document.getElementById('scenarioSkipBtn').style.display = 'none';
            } else {
                // Next step
                showActiveScenarioPrompt();
            }
        }
    }

    function startScenario(sc) {
        if (sc.os !== 'any' && sc.os !== currentOs) {
            const osName = sc.os === 'linux' ? 'Linux' : 'Windows';
            print(`⚠️  Ten scenariusz wymaga trybu ${osName}. Przełącz OS.`, 'warn');
            return;
        }
        activeScenario  = sc;
        activeStepIndex = 0;
        showActiveScenarioPrompt();
        renderScenarioList();
    }

    function showActiveScenarioPrompt() {
        if (!activeScenario) return;
        const step = activeScenario.steps[activeStepIndex];
        if (!step) return;

        print(`─── Zadanie: ${activeScenario.title} (krok ${activeStepIndex+1}/${activeScenario.steps.length}) ───`, 'warn');
        print(`▶  ${step.task}`, 'warn');
        if (step.hint) print(`   💡 Wskazówka: ${step.hint}`, 'dim');
        print('');

        updateScenarioProgress();
        document.getElementById('scenarioProgressWrap').style.display = '';
        document.getElementById('scenarioSkipBtn').style.display = '';
        document.getElementById('activeScenarioDesc').innerHTML =
            `<strong>${activeScenario.title}</strong><br><span class="text-muted">${activeScenario.desc}</span>`;
    }

    function updateScenarioProgress() {
        if (!activeScenario) return;
        const total  = activeScenario.steps.length;
        const done   = activeStepIndex;
        const pct    = total > 0 ? Math.round((done/total)*100) : 0;
        const bar    = document.getElementById('scenarioProgressBar');
        const label  = document.getElementById('scenarioProgressLabel');
        const step   = document.getElementById('scenarioStepLabel');
        if (bar)   bar.style.width = pct + '%';
        if (label) label.textContent = activeScenario.title;
        if (step)  step.textContent  = `Krok ${done}/${total}`;
    }

    function renderScenarioList() {
        const list = document.getElementById('scenarioList');
        if (!list) return;
        list.innerHTML = '';

        // Filter scenarios by current OS
        SCENARIOS.filter(sc => sc.os==='any' || sc.os===currentOs || true).forEach(sc => {
            const isActive    = activeScenario?.id === sc.id;
            const isCompleted = completedScen.has(sc.id);
            const isWrongOs   = sc.os !== 'any' && sc.os !== currentOs;

            const div = document.createElement('div');
            div.className = 'scenario-card' + (isActive?' active':'') + (isCompleted?' completed':'');
            div.innerHTML = `
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <span class="fw-bold">${escHtml(sc.title)}</span>
                    ${isCompleted ? '<span class="scenario-badge bg-success bg-opacity-15 text-success">✓ Ukończono</span>'
                      : isWrongOs ? `<span class="scenario-badge bg-secondary bg-opacity-15 text-muted">${sc.os.toUpperCase()}</span>` : ''}
                </div>
                <div class="text-muted mt-1" style="font-size:.72rem">${escHtml(sc.desc.slice(0,60))}…</div>`;

            if (!isCompleted) {
                div.addEventListener('click', () => startScenario(sc));
            }
            list.appendChild(div);
        });
    }

    function renderCommandList() {
        const el = document.getElementById('commandList');
        if (!el) return;
        const cmds = Object.keys(currentOs==='linux' ? LINUX_COMMANDS : WINDOWS_COMMANDS)
            .filter(c => c.length < 20)
            .slice(0,20);
        el.innerHTML = cmds.map(c => `<code class="me-2">${c}</code>`).join('');
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── OS Toggle ─────────────────────────────────────────────────────────────

    document.getElementById('osBtnLinux')?.addEventListener('click', () => switchOs('linux'));
    document.getElementById('osBtnWin')?.addEventListener('click', () => switchOs('windows'));

    function switchOs(os) {
        currentOs = os;
        document.getElementById('osBtnLinux')?.classList.toggle('active', os==='linux');
        document.getElementById('osBtnWin')?.classList.toggle('active', os==='windows');
        activeScenario  = null;
        activeStepIndex = 0;
        document.getElementById('scenarioProgressWrap').style.display = 'none';
        document.getElementById('scenarioSkipBtn').style.display = 'none';
        document.getElementById('activeScenarioDesc').innerHTML = '<i class="bi bi-info-circle me-1"></i>Wybierz scenariusz, aby zobaczyć zadanie.';
        renderScenarioList();
        renderCommandList();
        boot();
    }

    // ── Input Handling ────────────────────────────────────────────────────────

    termInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const val = termInput.value;
            termInput.value = '';
            executeCommand(val);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historyIndex < commandHistory.length - 1) {
                historyIndex++;
                termInput.value = commandHistory[historyIndex];
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex > 0) {
                historyIndex--;
                termInput.value = commandHistory[historyIndex];
            } else {
                historyIndex = -1;
                termInput.value = '';
            }
        } else if (e.key === 'Tab') {
            e.preventDefault();
            // Basic tab completion
            const val    = termInput.value;
            const reg    = currentOs === 'linux' ? LINUX_COMMANDS : WINDOWS_COMMANDS;
            const match  = Object.keys(reg).find(k => k.startsWith(val.toLowerCase()));
            if (match) termInput.value = match;
        } else if (e.key === 'l' && e.ctrlKey) {
            e.preventDefault();
            clear();
        }
    });

    // Click terminal to focus input
    document.getElementById('terminalWindow')?.addEventListener('click', () => termInput?.focus());

    // Buttons
    document.getElementById('scenarioClearBtn')?.addEventListener('click', () => {
        activeScenario  = null;
        activeStepIndex = 0;
        document.getElementById('scenarioProgressWrap').style.display = 'none';
        document.getElementById('scenarioSkipBtn').style.display = 'none';
        boot();
    });

    document.getElementById('scenarioSkipBtn')?.addEventListener('click', () => {
        activeScenario  = null;
        activeStepIndex = 0;
        document.getElementById('scenarioProgressWrap').style.display = 'none';
        document.getElementById('scenarioSkipBtn').style.display = 'none';
        print('Scenariusz pominięty.', 'dim');
        print('');
    });

    // ── Init ──────────────────────────────────────────────────────────────────

    boot();
    renderScenarioList();
    renderCommandList();
    termInput?.focus();

}());
