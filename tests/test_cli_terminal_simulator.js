/**
 * ZSEM Tech - CLI Lab & Terminal Simulator Automated Comprehensive Test Suite
 */

const fs = require('fs');
const path = require('path');

// Mock browser DOM & Storage for Node test environment
const storageMock = {};
global.localStorage = {
    getItem: (k) => storageMock[k] || null,
    setItem: (k, v) => { storageMock[k] = String(v); },
    removeItem: (k) => { delete storageMock[k]; },
    clear: () => { Object.keys(storageMock).forEach(k => delete storageMock[k]); }
};

global.FormData = class FormData {
    constructor() { this.data = {}; }
    append(k, v) { this.data[k] = v; }
    get(k) { return this.data[k]; }
};

global.fetch = async (url, options) => {
    return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, xp_awarded: 25, total_xp: 150 })
    };
};

function createMockElement(id = '', tag = 'div') {
    const el = {
        id,
        tag,
        value: '',
        _textContent: '',
        get textContent() {
            let res = this._textContent || '';
            if (this.children && this.children.length) {
                res += (res ? '\n' : '') + this.children.map(c => c.textContent).join('\n');
            }
            return res;
        },
        set textContent(v) {
            this._textContent = String(v);
            this.children = [];
        },
        get innerText() {
            return this.textContent;
        },
        set innerText(v) {
            this.textContent = v;
        },
        innerHTML: '',
        style: {},
        classList: {
            add: () => {},
            remove: () => {},
            toggle: () => {},
            contains: () => false
        },
        children: [],
        appendChild: (child) => {
            el.children.push(child);
            return child;
        },
        querySelectorAll: () => [],
        addEventListener: () => {},
        focus: () => {},
        remove: () => {}
    };
    return el;
}

const domMock = {};
global.document = {
    getElementById: (id) => {
        if (!domMock[id]) {
            domMock[id] = createMockElement(id);
        }
        return domMock[id];
    },
    querySelectorAll: (sel) => [],
    createElement: (tag) => createMockElement('', tag),
    body: createMockElement('body', 'body'),
    addEventListener: (evt, cb) => {
        if (evt === 'DOMContentLoaded') {
            cb();
        }
    }
};

global.window = {
    CLI_LAB_USER: {
        completedScenarios: ['inf02_ip_diag'],
        csrfToken: 'test_token_123'
    },
    zsemTerminal: null
};

// Load code
const jsCode = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'terminal_commands.js'), 'utf8');

// Evaluate code in scope
eval(jsCode);

console.log('🧪 Starting CLI Terminal Simulator Full Test Suite...\n');

let passedTests = 0;
let totalTests = 0;

function assert(condition, testName) {
    totalTests++;
    if (condition) {
        passedTests++;
        console.log(`  ✅ PASS: ${testName}`);
    } else {
        console.error(`  ❌ FAIL: ${testName}`);
        throw new Error(`Test failed: ${testName}`);
    }
}

// ── Test 1: Simulator Initialization & VFS ──
console.log('▶ TEST SUITE 1: TerminalSimulator & Virtual File System (VFS)');
const term = window.zsemTerminal;
assert(term !== null && term !== undefined, 'TerminalSimulator instantiated on DOMContentLoaded');
assert(term.vfs !== undefined, 'VirtualFileSystem initialized');
assert(term.net !== undefined, 'NetworkState initialized');

term.vfs.createDirectory('/home/student/testdir', true, false);
assert(term.vfs.getNode('/home/student/testdir', false) !== null, 'VFS createDirectory created directory node');

term.vfs.createFile('/home/student/testfile.txt', 'Hello ZSEM Lab\nLine 2\nLine 3', false);
const node = term.vfs.getNode('/home/student/testfile.txt', false);
assert(node && node.content.includes('Hello ZSEM'), 'VFS createFile created file with content');

// ── Test 2: Linux Commands & Pipeline Execution ──
console.log('\n▶ TEST SUITE 2: Linux Commands, Redirection & Pipelines');

term.switchOs('linux');
assert(term.currentOs === 'linux', 'Switched OS to Linux');

// executeCommand cat & grep
term.outputEl.innerHTML = '';
term.executeCommand('cat /home/student/testfile.txt');
assert(term.outputEl.textContent.includes('Hello ZSEM Lab'), 'Command `cat` output verified');

// Test pipeline forwarding
term.outputEl.innerHTML = '';
term.executeCommand('cat /home/student/testfile.txt | grep Line');
assert(term.outputEl.textContent.includes('Line 2') && term.outputEl.textContent.includes('Line 3'), 'Pipeline `cat | grep` output forwarded correctly');

// Test 3-step pipeline: cat | grep | wc -l
term.outputEl.innerHTML = '';
term.executeCommand('cat /home/student/testfile.txt | grep Line | wc -l');
assert(term.outputEl.textContent.trim().endsWith('2'), '3-step pipeline `cat | grep | wc -l` produced expected count 2');

// Test cut & sort
term.vfs.createFile('/tmp/users.txt', 'zeta:1\nalpha:2\nbeta:3', false);
term.outputEl.innerHTML = '';
term.executeCommand('cat /tmp/users.txt | cut -d: -f1 | sort');
assert(term.outputEl.textContent.includes('alpha\nbeta\nzeta'), 'Pipeline `cat | cut | sort` produced sorted output');

// Test chmod & chown
term.executeCommand('chmod 755 /home/student/testfile.txt');
const chmodNode = term.vfs.getNode('/home/student/testfile.txt', false);
assert(chmodNode.permissions === '0755', 'Command `chmod 755` updated permissions to 0755');

term.executeCommand('chown www-data:www-data /home/student/testfile.txt');
assert(chmodNode.owner === 'www-data' && chmodNode.group === 'www-data', 'Command `chown www-data:www-data` updated ownership');

// Test useradd & usermod
term.executeCommand('useradd anna');
const passwdNode = term.vfs.getNode('/etc/passwd', false);
assert(passwdNode.content.includes('anna:x:1002'), 'Command `useradd anna` updated /etc/passwd');

// Test systemctl
term.executeCommand('systemctl stop apache2');
assert(term.net.state.services.apache2.status === 'STOPPED', 'Command `systemctl stop apache2` set status to STOPPED');
term.executeCommand('systemctl start apache2');
assert(term.net.state.services.apache2.status === 'RUNNING', 'Command `systemctl start apache2` set status to RUNNING');

// ── Test 3: Windows Commands & PowerShell Engine ──
console.log('\n▶ TEST SUITE 3: Windows Commands & PowerShell Engine');

term.switchOs('windows');
assert(term.currentOs === 'windows', 'Switched OS to Windows');

// Test ipconfig
term.outputEl.innerHTML = '';
term.executeCommand('ipconfig /all');
assert(term.outputEl.textContent.includes('Windows IP Configuration') && term.outputEl.textContent.includes('IPv4 Address'), 'Command `ipconfig /all` output verified');

// Test sc & net
term.executeCommand('sc stop w3svc');
assert(term.net.state.services.w3svc.status === 'STOPPED', 'Command `sc stop w3svc` stopped w3svc service');
term.executeCommand('net start w3svc');
assert(term.net.state.services.w3svc.status === 'RUNNING', 'Command `net start w3svc` started w3svc service');

// Test PowerShell Sub-Shell
term.executeCommand('powershell');
assert(term.currentSubShell === 'powershell', 'Sub-Shell PowerShell activated');
term.outputEl.innerHTML = '';
term.executeCommand('Get-Service');
assert(term.outputEl.textContent.includes('w3svc') && term.outputEl.textContent.includes('Running'), 'PowerShell `Get-Service` listed running services');

term.outputEl.innerHTML = '';
term.executeCommand('Get-NetIPAddress');
assert(term.outputEl.textContent.includes('192.168.1.100'), 'PowerShell `Get-NetIPAddress` returned IP address');

term.executeCommand('exit');
assert(term.currentSubShell === null, 'Exited PowerShell Sub-Shell cleanly');

// ── Test 4: DiskPart Sub-Shell ──
console.log('\n▶ TEST SUITE 4: DiskPart Sub-Shell Engine');

term.executeCommand('diskpart');
assert(term.currentSubShell === 'diskpart', 'Sub-Shell DiskPart activated');

term.outputEl.innerHTML = '';
term.executeCommand('list disk');
assert(term.outputEl.textContent.includes('Disk 0') && term.outputEl.textContent.includes('Disk 1'), 'DiskPart `list disk` listed physical disks');

term.outputEl.innerHTML = '';
term.executeCommand('select disk 1');
assert(term.outputEl.textContent.includes('Disk 1 is now the selected disk'), 'DiskPart `select disk 1` selected disk');

term.outputEl.innerHTML = '';
term.executeCommand('create partition primary');
assert(term.outputEl.textContent.includes('succeeded in creating the specified partition'), 'DiskPart `create partition primary` succeeded');

term.outputEl.innerHTML = '';
term.executeCommand('format fs=ntfs quick');
assert(term.outputEl.textContent.includes('100 percent completed'), 'DiskPart `format fs=ntfs quick` formatted partition');

term.executeCommand('exit');
assert(term.currentSubShell === null, 'Exited DiskPart Sub-Shell cleanly');

// ── Test 5: MySQL Sub-Shell ──
console.log('\n▶ TEST SUITE 5: MySQL Sub-Shell Engine');

term.switchOs('linux');
term.executeCommand('mysql');
assert(term.currentSubShell === 'mysql', 'Sub-Shell MySQL activated');

term.outputEl.innerHTML = '';
term.executeCommand('SHOW DATABASES;');
assert(term.outputEl.textContent.includes('information_schema') && term.outputEl.textContent.includes('szkola'), 'MySQL `SHOW DATABASES;` returned schema list');

term.outputEl.innerHTML = '';
term.executeCommand('USE szkola;');
assert(term.outputEl.textContent.includes('Database changed'), 'MySQL `USE szkola;` selected database');

term.outputEl.innerHTML = '';
term.executeCommand('SELECT * FROM uczniowie;');
assert(term.outputEl.textContent.includes('Jan') && term.outputEl.textContent.includes('Kowalski'), 'MySQL `SELECT * FROM uczniowie;` returned table data');

term.executeCommand('exit;');
assert(term.currentSubShell === null, 'Exited MySQL Sub-Shell cleanly');

// ── Test 6: CKE Scenarios & Step Validators ──
console.log('\n▶ TEST SUITE 6: CKE Exam Scenarios Validation (36 Scenarios)');

term.selectScenarioById('inf02_ip_diag');
assert(term.activeScenario && term.activeScenario.id === 'inf02_ip_diag', 'Selected scenario inf02_ip_diag');
assert(term.activeScenarioStep === 0, 'Scenario starts at step 0');

term.executeCommand('ifconfig');
assert(term.activeScenarioStep === 1, 'Step 1 validated by `ifconfig`');

term.executeCommand('ping 192.168.1.1');
assert(term.activeScenarioStep === 2, 'Step 2 validated by `ping 192.168.1.1`');

term.executeCommand('nslookup google.pl');
assert(term.activeScenarioStep === 3, 'Step 3 validated by `nslookup google.pl`');

term.executeCommand('traceroute google.pl');
assert(term.activeScenario.completed === true, 'Scenario completed 100% after step 4');

console.log(`\n======================================================`);
console.log(`🏁 ALL ${passedTests}/${totalTests} TESTS PASSED WITH 100% SUCCESS!`);
console.log(`======================================================\n`);
process.exit(totalTests === passedTests ? 0 : 1);

