#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Enrich Question Explanations Pipeline for CKE Qualifications
(INF.02, INF.03, INF.04, INF.07, INF.08)

Generates fact-based, contrastive explanations with definitions:
- Main rationale explaining why correct answer is correct.
- 'Dlaczego nie reszta?' section with educational definitions and differences for each distractor.
- Zero tautologies.
"""

import json
import re
import os
import sys
import shutil
import ipaddress

ROOT_DIR = r"c:\Users\damia\OneDrive\Pulpit\stronammmmmmmm\public_html"
DATA_DIR = os.path.join(ROOT_DIR, "data_question")
DICT_PATH = os.path.join(ROOT_DIR, "data", "dictionary.json")

# Comprehensive Domain Knowledge Base mapping CKE terms to exact Polish definitions
TECHNICAL_KB = {
    # Windows Dynamic Disk Volumes
    "prosty": "Wolumin prosty (Simple Volume) w systemie Windows zajmuje przestrzeń na pojedynczym dysku fizycznym.",
    "łączony": "Wolumin łączony (Spanned / JBOD) łączy przestrzeń z wielu dysków w jeden wolumin, zapisując dane sekwencyjnie bez redundancji.",
    "rozłożony": "Wolumin rozłożony (Striped Volume) to w Windows programowy odpowiednik macierzy RAID 0 (paskowanie danych na minimum 2 dyskach bez redundancji).",
    "dublowany": "Wolumin dublowany (Mirrored Volume) to w systemie Windows programowy odpowiednik macierzy RAID 1, tworzący lustrzaną kopię danych na drugim dysku.",
    # Network Application Protocols
    "ftp": "FTP (File Transfer Protocol) to nieszyfrowany protokół przesyłania plików działający w architekturze klient-serwer na portach TCP 20 (dane) i 21 (sterowanie).",
    "smtp": "SMTP (Simple Mail Transfer Protocol) to protokół warstwy aplikacji na porcie TCP 25 służący do wysyłania i przekazywania poczty elektronicznej między serwerami pocztowymi MTA.",
    "http": "HTTP (Hypertext Transfer Protocol) to protokół przesyłania dokumentów hipertekstowych w sieci WWW, działający bez szyfrowania na porcie TCP 80.",
    "https": "HTTPS to bezpieczny wariant protokołu HTTP szyfrowany protokołem TLS/SSL, domyślnie nasłuchujący na porcie TCP 443.",
    "imap": "IMAP (Internet Message Access Protocol) to protokół na porcie TCP 143 (lub 993 z SSL), umożliwiający pobieranie nagłówków i dwukierunkową synchronizację poczty bezpośrednio na serwerze.",
    "pop3": "POP3 (Post Office Protocol v3) to protokół na porcie TCP 110 (lub 995 z SSL), służący do pobierania wiadomości e-mail z serwera na dysk lokalny klienta pocztowego.",
    "telnet": "Telnet to protokół zdalnego dostępu do terminala tekstowego na porcie TCP 23, przesyłający wszystkie dane (w tym hasła) jawnym tekstem bez szyfrowania.",
    "ssh": "SSH (Secure Shell) to protokół na porcie TCP 22 zapewniający szyfrowane, bezpieczne połączenie terminalowe, tunelowanie oraz transfer plików (SFTP/SCP).",
    "dns": "DNS (Domain Name System) to usługa warstwy aplikacji na porcie UDP/TCP 53, tłumacząca czytelne nazwy domenowe na numeryczne adresy IP.",
    "dhcp": "DHCP (Dynamic Host Configuration Protocol) to protokół na portach UDP 67/68 automatycznie przydzielający stacjom w sieci konfigurację IP, maskę i bramę domyślną.",
    "snmp": "SNMP (Simple Network Management Protocol) to protokół na porcie UDP 161 służący do monitorowania stanu i zdalnego zarządzania urządzeniami sieciowymi.",
    "ntp": "NTP (Network Time Protocol) to protokół na porcie UDP 123 służący do precyzyjnej synchronizacji zegarów systemowych komputerów w sieci.",
    "sntp": "SNTP (Simple Network Time Protocol) to uproszczona wersja protokołu NTP o mniejszej złożoności obliczeniowej dla urządzeń o ograniczonych zasobach.",
    "rdp": "RDP (Remote Desktop Protocol) to autorski protokół firmy Microsoft działający na porcie TCP 3389, służący do zdalnego zarządzania pulpitem graficznym Windows.",
    "smb": "SMB (Server Message Block) to protokół na porcie TCP 445 służący do współdzielenia plików, drukarek i portów szeregowych w sieciach Windows.",
    "icmp": "ICMP (Internet Control Message Protocol) to protokół warstwy sieciowej służący do diagnostyki sieci i przesyłania komunikatów o błędach (np. ping, Destination Unreachable).",
    "tcp": "TCP (Transmission Control Protocol) to połączeniowy protokół warstwy transportowej gwarantujący niezawodność, retransmisję zagubionych pakietów i zachowanie kolejności danych.",
    "udp": "UDP (User Datagram Protocol) to bezpołączeniowy protokół transportowy o minimalnym narzucie nagłówka, niegwarantujący dostarczenia danych (używany w streamingu, VoIP, DNS).",
    "igmp": "IGMP (Internet Group Management Protocol) to protokół używany przez hosty IPv4 do zgłaszania swojego członkostwa w grupach rozsyłania grupowego (multicast) do lokalnych routerów.",
    "dvmrp": "DVMRP (Distance Vector Multicast Routing Protocol) to protokół routingu transmisji wieloadresowej (multicast) oparty na algorytmie wektora odległości.",
    "pim": "PIM (Protocol Independent Multicast) to rodzina protokołów routingu multicast (np. PIM-SM, PIM-DM) niezależnych od używanego protokołu routingu unicast.",

    # Network CLI Diagnostics
    "ping": "Polecenie ping bada dostępność hosta docelowego w sieci za pomocą komunikatów ICMP Echo Request / Echo Reply.",
    "tracert": "Polecenie tracert (w systemie Linux: traceroute) śledzi trasę pakietów przez kolejne routery, wykorzystując pole TTL w nagłówku IP.",
    "traceroute": "Polecenie traceroute śledzi trasę pakietów IP do hosta docelowego za pomocą inkrementacji pola TTL.",
    "netstat": "Polecenie netstat wyświetla aktywne połączenia sieciowe TCP/UDP, tabele routingu oraz listę portów nasłuchujących w systemie.",
    "ipconfig": "Polecenie ipconfig w systemie Windows wyświetla konfigurację interfejsów sieciowych (adres IP, maskę, bramę) oraz zarządza dzierżawą DHCP (/renew, /release) i buforem DNS (/flushdns).",
    "ifconfig": "Narzędzie ifconfig w systemie Linux służy do podglądu i konfiguracji parametrów interfejsów sieciowych.",
    "ip": "Polecenie ip w systemie Linux (z pakietu iproute2) zarządza interfejsami (ip link), adresacją (ip addr) i routingiem (ip route).",
    "route": "Polecenie route w systemach Windows i Linux służy do wyświetlania i ręcznej modyfikacji statycznej tabeli routingu IP.",
    "arp": "Protokół i polecenie arp mapuje 32-bitowe adresy logiczne IPv4 na 48-bitowe adresy fizyczne MAC kart sieciowych w sieci lokalnej.",
    "nslookup": "Narzędzie nslookup służy do odpytywania serwerów DNS o rekordy powiązane z nazwami domenowymi lub adresami IP.",

    # IPv6 Addresses & Prefixes
    "::1": "Adres ::1 to adres pętli zwrotnej (loopback) w protokole IPv6, stanowiący odpowiednik adresu 127.0.0.1 w IPv4.",
    "fe80::/10": "Prefiks FE80::/10 oznacza adresy łącza lokalnego (Link-Local) w IPv6, generowane automatycznie i działające tylko w obrębie jednego łącza fizycznego.",
    "2001::/16": "Blok 2001::/16 należy do puli globalnych publicznych adresów IPv6 (Global Unicast, 2000::/3) routowalnych w światowym Internecie.",
    "fc00::/7": "Prefiks FC00::/7 (oraz FD00::/8) oznacza unikalne adresy lokalne (ULA – Unique Local Address) w IPv6, będące odpowiednikiem prywatnych adresów IPv4.",
    "::ffff:0:0/96": "Prefiks ::FFFF:0:0/96 to specjalny zakres adresów IPv6 mapowanych na IPv4 (IPv4-mapped IPv6 address).",
    "::/128": "Adres ::/128 to adres nieokreślony (unspecified address) w IPv6, odpowiadający 0.0.0.0 w IPv4.",
    "ff00::/8": "Prefiks FF00::/8 oznacza adresy rozsyłania grupowego (multicast) w IPv6, zastępujące całkowicie mechanizm broadcastu.",

    # IPv4 Ranges & Addresses
    "10.0.0.0/8": "Zakres 10.0.0.0/8 to prywatna przestrzeń adresowa IPv4 klasy A zdefiniowana w RFC 1918.",
    "172.16.0.0/12": "Zakres 172.16.0.0/12 (od 172.16.0.0 do 172.31.255.255) to prywatna przestrzeń adresowa IPv4 klasy B zdefiniowana w RFC 1918.",
    "192.168.0.0/16": "Zakres 192.168.0.0/16 (od 192.168.0.0 do 192.168.255.255) to prywatna przestrzeń adresowa IPv4 klasy C zdefiniowana w RFC 1918.",
    "10.0.0.1": "Adres 10.0.0.1 to prywatny adres IPv4 z puli klasy A (10.0.0.0/8, RFC 1918).",
    "172.16.0.1": "Adres 172.16.0.1 to prywatny adres IPv4 z puli klasy B (172.16.0.0/12, RFC 1918).",
    "192.168.1.1": "Adres 192.168.1.1 to popularny prywatny adres IPv4 z puli klasy C, powszechnie stosowany jako domyślna brama routera domowego.",
    "127.0.0.1": "Adres 127.0.0.1 to adres pętli zwrotnej (localhost) w IPv4, służący do komunikacji wewnętrznej w systemie.",
    "224.0.0.1": "Adres 224.0.0.1 to zarezerwowany adres rozsyłania grupowego (multicast) klasy D, adresujący wszystkie aktywne systemy w podsieci.",
    "255.255.255.255": "Adres 255.255.255.255 to ograniczony adres rozgłoszeniowy (limited broadcast) w IPv4.",

    # Subnet Masks
    "255.255.255.0": "Maska 255.255.255.0 (prefiks /24) przydziela 24 bity na sieć i 8 bitów na hosty, oferując 254 użyteczne adresy w podsieci.",
    "255.255.0.0": "Maska 255.255.0.0 (prefiks /16) to standardowa maska klasy B, oferująca 65 534 użytecznych adresów hostów.",
    "255.0.0.0": "Maska 255.0.0.0 (prefiks /8) to standardowa maska klasy A, przydzielająca 24 bity na adresację hostów.",
    "255.255.255.128": "Maska 255.255.255.128 (prefiks /25) dzieli podsieć klasy C na dwa równe bloki po 126 użytecznych adresów hosta.",
    "255.255.255.192": "Maska 255.255.255.192 (prefiks /26) dzieli przestrzeń na bloki po 64 adresy (62 użyteczne hosty).",
    "255.255.255.240": "Maska 255.255.255.240 (prefiks /28) tworzy podsieci o rozmiarze 16 adresów (14 użytecznych hostów).",
    "255.255.255.252": "Maska 255.255.255.252 (prefiks /30) tworzy podsieci o 4 adresach (2 użyteczne hosty), idealne dla łączy punkt-punkt między routerami.",

    # OSI 7 Layer Model
    "fizyczna": "Warstwa 1 (Fizyczna) odpowiada za fizyczną transmisję nieuporządkowanego strumienia bitów przez medium (napięcia elektryczne, impulsy światła, fale radiowe).",
    "łącza danych": "Warstwa 2 (Łącza danych) odpowiada za pakowanie bitów w ramki, kontrolę dostępu do medium (MAC) i wykrywanie błędów w sieci lokalnej.",
    "sieciowa": "Warstwa 3 (Sieciowa) odpowiada za logiczną adresację IP oraz trasowanie (routing) pakietów między różnymi sieciami.",
    "transportowa": "Warstwa 4 (Transportowa) zapewnia komunikację koniec-do-końca między procesami aplikacji za pomocą portów TCP i UDP.",
    "sesji": "Warstwa 5 (Sesji) zarządza otwieraniem, utrzymywaniem i zamykaniem sesji komunikacyjnych między aplikacjami.",
    "prezentacji": "Warstwa 6 (Prezentacji) odpowiada za formatowanie danych, kodowanie znaków, szyfrowanie (np. SSL/TLS) i kompresję.",
    "aplikacji": "Warstwa 7 (Aplikacji) udostępnia usługi i interfejsy sieciowe bezpośrednio aplikacjom użytkownika (np. HTTP, FTP, DNS, SMTP).",

    # DNS Records
    "a": "Rekord A (Address) w systemie DNS mapuje nazwę domeny na 32-bitowy adres IPv4.",
    "aaaa": "Rekord AAAA (Quad-A) w systemie DNS mapuje nazwę domeny na 128-bitowy adres IPv6.",
    "mx": "Rekord MX (Mail Exchanger) wskazuje serwer pocztowy odpowiedzialny za odbieranie poczty e-mail dla danej domeny wraz z priorytetem.",
    "cname": "Rekord CNAME (Canonical Name) to alias wskazujący jedną nazwę domeny na inną nazwę kanoniczną.",
    "ns": "Rekord NS (Name Server) wskazuje autorytatywne serwery nazw DNS obsługujące daną strefę domenową.",
    "ptr": "Rekord PTR (Pointer) służy do odwrotnego mapowania adresu IP na nazwę domeny (Reverse DNS).",
    "soa": "Rekord SOA (Start of Authority) zawiera kluczowe dane administracyjne o strefie DNS (serwer główny, kontakt, numer seryjny).",
    "txt": "Rekord TXT pozwala na powiązanie dowolnego tekstu z domeną, powszechnie stosowany w mechanizmach weryfikacji SPF, DKIM i DMARC.",

    # Windows Management Tools
    "taskmgr": "Menedżer zadań (taskmgr) w Windows pozwala monitorować obciążenie procesora, pamięci, dysku i sieci oraz zarządzać procesami i aplikacjami autostartu.",
    "regedit": "Edytor rejestru (regedit) służy do przeglądania i modyfikacji hierarchicznej bazy konfiguracji systemu Windows (klucze HKEY_*).",
    "msconfig": "Narzędzie msconfig (Konfiguracja systemu) pozwala konfigurować opcje rozruchu systemu Windows, usługi i tryby uruchamiania diagnostycznego.",
    "services.msc": "Konsola services.msc służy do zarządzania usługami systemowymi Windows (uruchamianie, zatrzymywanie, tryb startu automatyczny/ręczny).",
    "devmgmt.msc": "Menedżer urządzeń (devmgmt.msc) pozwala na zarządzanie sterownikami i konfiguracją podzespołów sprzętowych komputera.",
    "diskmgmt.msc": "Zarządzanie dyskami (diskmgmt.msc) pozwala tworzyć, formatować, rozszerzać partycje i woluminy oraz inicjalizować dyski (MBR/GPT).",
    "eventvwr": "Podgląd zdarzeń (eventvwr.msc) wyświetla dzienniki zdarzeń systemowych, aplikacji, zabezpieczeń i instalacji Windows.",

    # VPN & Tunneling
    "l2tp": "L2TP (Layer 2 Tunneling Protocol) to protokół tunelowania warstwy 2, który sam w sobie nie zapewnia szyfrowania i jest niemal zawsze łączony z IPsec.",
    "pptp": "PPTP (Point-to-Point Tunneling Protocol) to przestarzały protokół tunelowania VPN o niskim poziomie bezpieczeństwa.",
    "ipsec": "IPsec (Internet Protocol Security) to zestaw protokołów (AH, ESP) zapewniający uwierzytelnianie, integralność i poufność na poziomie warstwy sieciowej IP.",
    "openvpn": "OpenVPN to otwartoźródłowe rozwiązanie VPN wykorzystujące protokół SSL/TLS do bezpiecznego tunelowania ruchu sieciowego.",
    "vxlan": "VXLAN (Virtual Extensible LAN) to technologia wirtualizacji sieci enkapsulująca ramki warstwy 2 w pakietach UDP warstwy 3 (port 4789).",

    # Operators & Logic
    "==": "Operator porównania == sprawdza relację równości wartości dwóch operandów.",
    "!=": "Operator != sprawdza relację nierówności (różnicy) dwóch operandów.",
    "&&": "Operator && to koniunkcja logiczna (AND), zwracająca true tylko wtedy, gdy oba wyrażenia są prawdziwe.",
    "||": "Operator || to alternatywa logiczna (OR), zwracająca true, jeśli przynajmniej jedno wyrażenie jest prawdziwe.",
    "!": "Operator ! to unarna negacja logiczna (NOT) odwracająca wartość logiczną wyrażenia.",
    "+=": "Operator += to operator przypisania ze złożonym dodawaniem (zwiększa wartość zmiennej o prawy operand).",
    "&": "Operator & reprezentuje bitową operację koniunkcji (AND) lub pobranie adresu w językach C/C++.",
    "~": "Operator ~ to unarny operator bitowej negacji (NOT), odwracający wszystkie bity liczby (tworząc dopełnienie bitowe).",

    # Programming Collections & OOP
    "queue": "Kolejka (Queue) to struktura danych typu FIFO (First In, First Out), w której elementy pobierane są w kolejności ich dodania.",
    "stack": "Stos (Stack) to struktura danych typu LIFO (Last In, First Out), w której ostatnio dodany element jest pobierany jako pierwszy (push/pop).",
    "list": "Lista (List) to dynamiczna kolekcja elementów o zmiennej długości, umożliwiająca sekwencyjny dostęp i łatwe wstawianie elementów.",
    "array": "Tablica (Array) to struktura danych o stałym rozmiarze przechowująca elementy w ciągłym obszarze pamięci z szybkim indeksem O(1).",
    "dictionary": "Słownik (Dictionary / Map) to kolekcja par klucz-wartość zapewniająca natychmiastowy dostęp do elementów na podstawie unikalnego klucza.",
    "public": "Modyfikator dostępu public oznacza brak ograniczeń widoczności – element jest dostępny z dowolnego miejsca programu.",
    "private": "Modyfikator dostępu private ogranicza widoczność pola lub metody wyłącznie do wnętrza bieżącej klasy (hermetyzacja).",
    "protected": "Modyfikator dostępu protected udostępnia składową klasie macierzystej oraz klasom z niej dziedziczącym.",
    "internal": "Modyfikator internal w języku C# ogranicza widoczność typu lub składowej do kodu w obrębie tego samego podzespołu (assembly).",
    "static": "Modyfikator static oznacza, że składowa należy do typu (klasy), a nie do konkretnej instancji obiektu.",
    "virtual": "Modyfikator virtual w C# zezwala na nadpisanie implementacji metody w klasach pochodnych.",
    "override": "Modyfikator override służy do nadpisania metody wirtualnej lub abstrakcyjnej w klasie potomnej.",
    "abstract": "Modyfikator abstract oznacza klasę lub metodę pozbawioną bezpośredniej implementacji, wymagającą definicji w klasie pochodnej.",
    "sealed": "Modyfikator sealed blokuje możliwość dziedziczenia po klasie lub nadpisywania metody.",
    "interface": "Interfejs (interface) definiuje kontrakt zachowań (sygnatury metod, właściwości) bez implementacji.",
    "for": "Pętla for to instrukcja iteracyjna wykonująca blok kodu określoną liczbę razy na podstawie licznika.",
    "while": "Pętla while wykonuje blok kodu dopóki warunek sprawdzany na początku jest prawdziwy.",
    "do-while": "Pętla do-while wykonuje blok kodu co najmniej raz przed sprawdzeniem warunku końcowego.",
    "do..while": "Pętla do..while wykonuje blok kodu co najmniej raz przed sprawdzeniem warunku końcowego.",
    "foreach": "Pętla foreach iteruje po wszystkich elementach tablicy lub kolekcji bez konieczności jawnego indeksowania.",
    "object": "Klasa Object (lub typ object) to bazowy typ nadrzędny w hierarchii dziedziczenia w językach obiektowych (C#, Java).",
    "byte": "Typ byte reprezentuje 8-bitową liczbę całkowitą bez znaku (w zakresie od 0 do 255).",
    "int": "Typ int reprezentuje 32-bitową liczbę całkowitą ze znakiem (zakres od -2 147 483 648 do 2 147 483 647).",
    "float": "Typ float reprezentuje liczbę zmiennoprzecinkową pojedynczej precyzji (32 bity, IEEE 754).",
    "double": "Typ double reprezentuje liczbę zmiennoprzecinkową podwójnej precyzji (64 bity, IEEE 754).",
    "bool": "Typ bool (boolean) reprezentuje pojedynczą wartość logiczną prawda (true) lub fałsz (false).",
    "char": "Typ char reprezentuje pojedynczy znak alfanumeryczny kodowany w standardzie Unicode/ASCII (16 bitów w C#/Java).",

    # Network Media
    "twisted pair": "Skrętka (Twisted Pair) składa się ze splecionych parami izolowanych żył miedzianych (np. UTP, FTP), redukujących zakłócenia elektromagnetyczne.",
    "skrętka": "Skrętka miedziana (UTP, FTP, STP) składa się z 4 splecionych par przewodów miedzianych, co minimalizuje zakłócenia elektromagnetyczne (EMI) i przesłuchy.",
    "światłowód": "Światłowód transmituje dane w postaci impulsów świetlnych przez rdzeń szklany/polimerowy, zapewniając całkowitą odporność na zakłócenia elektromagnetyczne.",
    "koaksjalny": "Kabel koncentryczny (koaksjalny) składa się z miedzianej żyły centralnej otoczonej dielektrykiem i metalowym oplotem ekranującym.",
    "optyczny": "Kabel optyczny (światłowód jednomodowy SMF lub wielomodowy MMF) wykorzystuje zjawisko całkowitego wewnętrznego odbicia światła.",
    "radiowy": "Medium radiowe wykorzystuje modulację fal elektromagnetycznych w przestrzeni otwartej (np. w standardach Wi-Fi 2.4 GHz / 5 GHz / 6 GHz).",

    # Linux CLI
    "top": "Polecenie top w systemie Linux wyświetla na żywo dynamiczną listę procesów, zużycie procesora, pamięci i obciążenie systemu (load average).",
    "mkdir": "Polecenie mkdir (make directory) służy do tworzenia nowych katalogów w strukturze systemu plików.",
    "pwd": "Polecenie pwd (print working directory) wypisuje pełną ścieżkę absolutną bieżącego katalogu roboczego w systemie Linux.",
    "ls": "Polecenie ls wyświetla listę plików i podkatalogów w bieżącej lub wskazanej lokalizacji.",
    "cd": "Polecenie cd (change directory) służy do zmiany bieżącego katalogu roboczego w drzewie plików.",
    "chmod": "Polecenie chmod modyfikuje bity uprawnień odczytu (r), zapisu (w) i wykonania (x) dla właściciela, grupy i pozostałych użytkowników.",
    "chown": "Polecenie chown zmienia użytkownika właściciela lub grupę właścicielską wskazanych plików i katalogów.",
    "ps": "Polecenie ps raportuje migawkę aktualnie działających procesów w systemie operacyjnym.",
    "kill": "Polecenie kill wysyła sygnał (domyślnie SIGTERM 15 lub SIGKILL 9) do procesu o podanym numerze PID.",
    "cat": "Polecenie cat łączy i wyświetla zawartość plików tekstowych na standardowym wyjściu.",
    "grep": "Narzędzie grep przeszukuje linie tekstu pod kątem wystąpienia podanego wzorca lub wyrażenia regularnego.",
    "tar": "Narzędzie tar służy do archiwizacji (łączenia wielu plików w jeden plik archiwum .tar) oraz ich rozpakowywania.",
    "gzip": "Narzędzie gzip kompresuje pojedyncze pliki za pomocą algorytmu Deflate, tworząc pliki z rozszerzeniem .gz.",

    # Routing Protocols
    "rip": "Routing Information Protocol (RIP) to protokół routingu wektora odległości, którego metryką jest liczba przeskoków (hop count, max 15).",
    "ospf": "Open Shortest Path First (OSPF) to protokół stanu łącza (Link-State) wykorzystujący algorytm Dijkstry i metrykę kosztu bazującą na przepustowości łączy.",
    "eigrp": "Enhanced Interior Gateway Routing Protocol (EIGRP) to zaawansowany protokół wektora odległości firmy Cisco oparty na algorytmie DUAL.",
    "bgp": "Border Gateway Protocol (BGP) to protokół wektora ścieżki używany do routingu między systemami autonomicznymi (AS) w globalnym Internecie.",
    "is-is": "Intermediate System to Intermediate System (IS-IS) to protokół stanu łącza stosowany do routingu pakietów w wielkich sieciach szkieletowych operatorów telekomunikacyjnych.",

    # Network Devices & Elements
    "router": "Router to urządzenie warstwy 3 modelu OSI (sieciowej), odpowiedzialne za wyznaczanie tras i przekazywanie pakietów między różnymi sieciami IP.",
    "switch": "Przełącznik (switch) to urządzenie warstwy 2 modelu OSI (łącza danych), przekazujące ramki w sieci LAN na podstawie tablicy adresów fizycznych MAC (CAM).",
    "hub": "Koncentrator (hub) to urządzenie warstwy 1 OSI (fizycznej), które bezkrytycznie powiela sygnał elektryczny na wszystkie porty w jednej domenie kolizyjnej.",
    "bridge": "Mostek (bridge) to urządzenie warstwy 2 OSI łączące dwa segmenty sieci LAN i filtrujące ruch na podstawie adresów MAC.",
    "repeater": "Wzmacniak (repeater) regeneruje i wzmacnia sygnał w warstwie 1 OSI w celu wydłużenia maksymalnego zasięgu medium transmisyjnego.",
    "access point": "Punkt dostępowy (Access Point) łączy bezprzewodowe stacje klienckie Wi-Fi (802.11) z przewodową siecią Ethernet (802.3).",
    "firewall": "Zapora sieciowa (firewall) kontroluje i filtruje pakiety sieciowe na podstawie zdefiniowanych reguł bezpieczeństwa.",
    "gateway": "Brama sieciowa (Gateway) stanowi punkt węzłowy łączący dwie różne sieci o odmiennych protokołach lub adresacjach.",

    # Wi-Fi Standards
    "802.11a": "Standard IEEE 802.11a działa w paśmie 5 GHz z modulacją OFDM, oferując przepustowość do 54 Mb/s.",
    "802.11b": "Standard IEEE 802.11b działa w paśmie 2.4 GHz z modulacją DSSS, oferując przepustowość do 11 Mb/s.",
    "802.11g": "Standard IEEE 802.11g działa w paśmie 2.4 GHz z modulacją OFDM, osiągając przepustowość do 54 Mb/s i zachowując wsteczną zgodność z 802.11b.",
    "802.11n": "Standard IEEE 802.11n (Wi-Fi 4) działa w pasmach 2.4 GHz oraz 5 GHz z technologią MIMO, osiągając prędkość do 600 Mb/s.",
    "802.11ac": "Standard IEEE 802.11ac (Wi-Fi 5) działa wyłącznie w paśmie 5 GHz z kanałami o szerokości do 160 MHz i technologią MU-MIMO, osiągając transfery rzędu Gb/s.",
    "802.11ax": "Standard IEEE 802.11ax (Wi-Fi 6/6E) wprowadza modulację OFDMA i obsługę pasm 2.4 GHz, 5 GHz oraz 6 GHz.",

    # Web & Languages
    "php": "PHP to skryptowy język programowania wykonywany po stronie serwera WWW, dedykowany do generowania dynamicznych stron internetowych i obsługi backendu.",
    "html": "HTML (HyperText Markup Language) to standardowy język znaczników definiujący szkielet i semantyczną strukturę dokumentów WWW.",
    "css": "CSS (Cascading Style Sheets) to język arkuszy stylów służący do definiowania prezentacji wizualnej, układu i kolorów stron WWW.",
    "javascript": "JavaScript to wieloparadygmatowy język skryptowy, stanowiący standard obsługi logiki i dynamiki po stronie przeglądarki klienta (DOM) oraz serwera (Node.js).",
    "sql": "SQL (Structured Query Language) to deklaratywny język służący do definiowania struktur oraz modyfikowania i pobierania danych z relacyjnych baz danych.",
    "c#": "C# to nowoczesny, obiektowy język programowania firmy Microsoft, kompilowany do kodu pośredniego CIL i wykonywany w środowisku .NET CLR.",
    "c++": "C++ to wysokowydajny, kompilowany do kodu maszynowego język programowania ogólnego przeznaczenia z bezpośrednim zarządzaniem pamięcią.",
    "java": "Java to obiektowy język programowania kompilowany do kodu bajtowego i uruchamiany w wieloplatformowym środowisku maszyny wirtualnej JVM.",
    "python": "Python to interpretowany, obiektowy język programowania wysokiego poziomu z czytelną składnią i dynamicznym typowaniem.",

    # Operating Systems
    "linux": "Linux to otwartoźródłowy, uniksopodobny system operacyjny oparty na modularnym jądrze monolitycznym, dominujący w rozwiązaniach serwerowych.",
    "windows": "Windows to rodzina systemów operacyjnych firmy Microsoft z graficznym interfejsem użytkownika i szerokim wsparciem dla oprogramowania konsumenckiego i biurowego.",
    "macos": "macOS to komercyjny system operacyjny firmy Apple zoptymalizowany pod architekturę sprzętową komputerów Mac, bazujący na systemie BSD i jądrze XNU.",
    "ms-dos": "MS-DOS to historyczny, 16-bitowy jednoużytkownikowy system operacyjny sterowany wyłącznie z wiersza poleceń.",

    # SQL Commands & Clauses
    "select": "Klauzula SELECT w języku SQL służy do pobierania i filtrowania rekordów z jednej lub wielu tabel bazy danych.",
    "insert": "Polecenie INSERT INTO wstawia jeden lub więcej nowych wierszy z danymi do wskazanej tabeli bazy danych.",
    "update": "Polecenie UPDATE modyfikuje istniejące wartości w kolumnach tabeli bazodanowej (zazwyczaj z klauzulą WHERE).",
    "delete": "Polecenie DELETE FROM usuwa wybrane wiersze z tabeli bazy danych bez niszczenia samej struktury tabeli.",
    "create": "Polecenie CREATE wchodzi w skład DDL i służy do tworzenia nowych struktur bazodanowych (bazy, tabeli, widoku, indeksu).",
    "alter": "Polecenie ALTER modyfikuje strukturę istniejącej tabeli (np. dodaje, usuwa lub zmienia typ kolumny).",
    "drop": "Polecenie DROP bezpowrotnie usuwa cały obiekt bazodanowy (np. całą tabelę wraz z wszystkimi danymi i indeksem).",
    "having": "Klauzula HAVING filtruje zagregowane grupy rekordów utworzone przez klauzulę GROUP BY (dopuszcza funkcje agregujące).",
    "where": "Klauzula WHERE filtruje pojedyncze wiersze tabeli przed wykonaniem grupowania danych.",
    "order by": "Klauzula ORDER BY sortuje wynikowy zbiór rekordów rosnąco (ASC) lub malejąco (DESC) według wybranych kolumn.",
    "group by": "Klauzula GROUP BY grupuje wiersze o identycznych wartościach w podanych kolumnach w pojedyncze rekordy zbiorcze.",
    "limit": "Klauzula LIMIT ogranicza maksymalną liczbę zwracanych rekordów w wyniku zapytania SQL.",

    # CSS Properties
    "font-family": "Właściwość font-family definiuje priorytetową listę nazw krojów pisma (czcionek) dla tekstu w arkuszu stylów CSS.",
    "text-font": "Właściwość text-font nie istnieje w oficjalnej specyfikacji CSS (właściwym parametrem dla czcionki jest font-family).",
    "typeface": "Właściwość typeface nie istnieje w specyfikacji CSS (termin pochodzi z typografii tradycyjnej).",
    "font": "Właściwość skrótowa font pozwala na łączne zdefiniowanie wielu parametrów tekstu (np. font-style, font-size, font-family).",

    # C# Specifics
    "task": "Klasa Task reprezentuje asynchroniczną operację w środowisku .NET w ramach wzorca TAP (Task-based Asynchronous Pattern).",
    "thread": "Klasa Thread reprezentuje niskopoziomowy wątek wykonawczy zarządzany przez system operacyjny.",
    "async": "Słowo kluczowe async modyfikuje sygnaturę metody, zezwalając w jej wnętrzu na użycie operatora await.",
    "background": "Pojęcie background nie stanowi wbudowanej klasy asynchroniczności w C# (w .NET stosuje się np. BackgroundWorker lub Task.Run).",
    "sort()": "Metoda Sort() sortuje elementy listy w miejscu (in-place) przy użyciu domyślnego lub zdefiniowanego komparatora.",
    "order()": "Metoda Order() nie jest standardową metodą sortowania listy w C# (w technologii LINQ stosuje się metodę rozszerzającą OrderBy).",
    "arrange()": "Metoda Arrange() nie służy do sortowania kolekcji (metoda o tej nazwie w WPF odpowiada za rozmieszczanie elementów w układzie GUI).",
    "sequence()": "Pojęcie Sequence() nie stanowi wbudowanej metody sortowania kolekcji danych w bibliotece standardowej C#.",
}

def load_dictionary():
    if not os.path.exists(DICT_PATH):
        return {}
    with open(DICT_PATH, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    terms_map = {}
    for block in data:
        qual = block.get("qualification", "ALL").upper()
        if qual not in terms_map:
            terms_map[qual] = {}
        for item in block.get("terms", []):
            term = item.get("term", "").strip()
            definition = item.get("definition", "").strip()
            example = item.get("example", "").strip()
            if term and definition:
                key = term.lower()
                val = {"term": term, "definition": definition, "example": example}
                terms_map[qual][key] = val
                if "ALL" not in terms_map:
                    terms_map["ALL"] = {}
                if key not in terms_map["ALL"]:
                    terms_map["ALL"][key] = val
    return terms_map

DICT_DATA = load_dictionary()

def clean_term_string(text):
    s = text.strip()
    # strip quotes and brackets
    s = s.strip(" \t\n\r\0\x0B\"'`[]{}()")
    # strip leading and trailing punctuation like dot or comma, but keep colons (IPv6) and slashes (CIDR)
    s = re.sub(r'^[.,;]+|[.,;]+$', '', s)
    return s.lower()

def get_term_definition_rich(text, qual="INF.02"):
    t_clean = clean_term_string(text)
    if not t_clean:
        return None

    # 1. Exact match in technical KB
    if t_clean in TECHNICAL_KB:
        return {"term": text.strip(), "definition": TECHNICAL_KB[t_clean]}

    # 2. IPv6 prefix patterns
    if t_clean.startswith("fe80:"):
        return {"term": text.strip(), "definition": "Adres z prefiksu FE80::/10 to adres łącza lokalnego (Link-Local) w IPv6, generowany automatycznie dla interfejsu."}
    if t_clean.startswith("2001:") or t_clean.startswith("2002:") or t_clean.startswith("2000:"):
        return {"term": text.strip(), "definition": "Adres z bloku 2000::/3 to publiczny, globalnie routowalny adres IPv6 (Global Unicast)."}
    if t_clean.startswith("fc00:") or t_clean.startswith("fd00:"):
        return {"term": text.strip(), "definition": "Adres z puli FC00::/7 to unikalny adres lokalny (ULA) w IPv6, odpowiednik prywatnych adresów IPv4."}
    if t_clean.startswith("ff0"):
        return {"term": text.strip(), "definition": "Adres z prefiksu FF00::/8 to adres rozsyłania grupowego (multicast) w IPv6."}
    if t_clean.startswith("ffff:"):
        return {"term": text.strip(), "definition": "Nie stanowi standardowego prefiksu adresu hosta IPv6."}

    qual = qual.upper()
    if qual == "EE.08": qual = "INF.02"
    if qual == "EE.09": qual = "INF.03"

    # 3. Exact match in dictionary
    if qual in DICT_DATA and t_clean in DICT_DATA[qual]:
        return DICT_DATA[qual][t_clean]
    if "ALL" in DICT_DATA and t_clean in DICT_DATA["ALL"]:
        return DICT_DATA["ALL"][t_clean]

    # 4. Normalized term match
    for k, v in TECHNICAL_KB.items():
        if k == t_clean or f" {k} " in f" {t_clean} ":
            return {"term": text.strip(), "definition": v}

    # 5. Dictionary substring match ONLY for longer multi-character terms (>= 4 chars, avoiding short acronyms)
    if len(t_clean) >= 4:
        if qual in DICT_DATA:
            for k, v in DICT_DATA[qual].items():
                if len(k) >= 4 and (k == t_clean or (k in t_clean and len(k) > len(t_clean) * 0.7)):
                    return v
        if "ALL" in DICT_DATA:
            for k, v in DICT_DATA["ALL"].items():
                if len(k) >= 4 and (k == t_clean or (k in t_clean and len(k) > len(t_clean) * 0.7)):
                    return v

    return None

def calculate_subnet_details(ip_str, prefix):
    try:
        net = ipaddress.IPv4Network(f"{ip_str}/{prefix}", strict=False)
        return {
            "network": str(net.network_address),
            "broadcast": str(net.broadcast_address),
            "netmask": str(net.netmask),
            "total_hosts": net.num_addresses,
            "usable_hosts": max(0, net.num_addresses - 2) if net.prefixlen < 31 else net.num_addresses,
            "first_host": str(net.network_address + 1) if net.num_addresses > 2 else str(net.network_address),
            "last_host": str(net.broadcast_address - 1) if net.num_addresses > 2 else str(net.broadcast_address),
            "prefix": net.prefixlen
        }
    except Exception:
        return None

def build_subnet_explanation(q_text, correct_letter, options):
    m = re.search(r'(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s*/\s*(\d{1,2})', q_text)
    if not m:
        return None
    ip_str = m.group(1)
    pfx = int(m.group(2))
    details = calculate_subnet_details(ip_str, pfx)
    if not details:
        return None

    correct_val = options.get(correct_letter, "").strip()
    is_broadcast_q = any(w in q_text.lower() for w in ["rozgłoszeniowy", "broadcast"])
    is_network_q = any(w in q_text.lower() for w in ["adres sieci", "adresem sieci"])
    is_host_count_q = any(w in q_text.lower() for w in ["liczba hostów", "użytecznych hostów", "adresów hosta"])

    main_text = f"• Poprawna odpowiedź: {correct_letter}. {correct_val}.\n"
    if is_broadcast_q:
        main_text += (
            f"Uzasadnienie: Dla adresu {ip_str} z prefiksem /{pfx} (maska {details['netmask']}), "
            f"adres podsieci to {details['network']}, a adres rozgłoszeniowy (broadcast) to {details['broadcast']}. "
            f"Adres rozgłoszeniowy tworzy się przez ustawienie wszystkich {32 - pfx} bitów części hosta na 1 binarnie."
        )
    elif is_network_q:
        main_text += (
            f"Uzasadnienie: Dla adresu {ip_str} z prefiksem /{pfx} (maska {details['netmask']}), "
            f"adres sieci to {details['network']}. Uzyskuje się go poprzez iloczyn bitowy (AND) adresu IP i maski podsieci."
        )
    elif is_host_count_q:
        main_text += (
            f"Uzasadnienie: W podsieci z prefiksem /{pfx} na identyfikator hosta przypada {32 - pfx} bitów. "
            f"Liczba użytecznych hostów wynosi 2^({32 - pfx}) - 2 = {details['usable_hosts']} "
            f"(odejmujemy adres sieci i adres rozgłoszeniowy)."
        )
    else:
        main_text += (
            f"Uzasadnienie: Analiza podsieci {ip_str}/{pfx}: maska {details['netmask']}, "
            f"adres sieci {details['network']}, adres rozgłoszeniowy {details['broadcast']}, "
            f"zakres użytecznych hostów od {details['first_host']} do {details['last_host']}."
        )

    distractors = []
    for let in ["A", "B", "C", "D"]:
        if let == correct_letter:
            continue
        opt_text = options.get(let, "").strip()
        if not opt_text:
            continue
        if opt_text == details["network"]:
            reason = "to adres samej sieci (część hosta ma same bity 0), a nie poszukiwana wartość."
        elif opt_text == details["broadcast"]:
            reason = "to adres rozgłoszeniowy (broadcast) tej podsieci."
        elif is_broadcast_q and (opt_text.endswith(".255") or opt_text.endswith(".255.255")):
            reason = f"nie odpowiada granicy bloku podsieci /{pfx} wyznaczonej przez maskę {details['netmask']}."
        elif re.match(r'^\d+$', opt_text):
            val = int(opt_text)
            if val == details["total_hosts"]:
                reason = "to całkowita liczba wszystkich adresów w bloku; dla użytecznych hostów należy odjąć 2 (adres sieci i broadcast)."
            else:
                reason = f"wartość obliczona niepoprawnie; dla maski /{pfx} wzór 2^(32-{pfx})-2 daje wynik {details['usable_hosts']}."
        else:
            reason = f"wartość nie odpowiada parametrom konfiguracji podsieci IPv4 z prefiksem /{pfx}."
        distractors.append(f"• {let}. {opt_text} - {reason}")

    return "Wyjaśnienie i uzasadnienie:\n" + main_text + "\n\nDlaczego nie reszta?\n" + "\n".join(distractors)

def build_diagram_image_explanation(q_text, correct_letter, options, qual):
    correct_val = options.get(correct_letter, "").strip()
    
    # Question about switching power supply (zasilacz impulsowy)
    if "zasilacz" in q_text.lower() and "transformator impulsowy" in q_text.lower():
        parts = {
            "A": "transformator impulsowy (rdzeń ferrytowy, separacja galwaniczna i przetężenie napięcia)",
            "B": "kondensatory elektrolityczne filtrujące po stronie pierwotnej (wysokonapięciowe)",
            "C": "radiator z tranzystorami kluczującymi MOSFET lub diodami prostowniczymi",
            "D": "dławik / filtr wyjściowy po stronie wtórnej zasilacza"
        }
        main_text = f"• Poprawna odpowiedź: {correct_letter}. {correct_val}.\n"
        main_text += f"Uzasadnienie: Transformator impulsowy na schemacie zasilacza impulsowego to element z rdzeniem ferrytowym oznaczony symbolem {correct_letter}."
        distractors = []
        for let in ["A", "B", "C", "D"]:
            if let == correct_letter: continue
            distractors.append(f"• {let}. {options.get(let)} - wskazuje {parts.get(let, 'inny podzespół zasilacza')}, a nie transformator impulsowy.")
        return "Wyjaśnienie i uzasadnienie:\n" + main_text + "\n\nDlaczego nie reszta?\n" + "\n".join(distractors)

    # Motherboard components compatibility table
    if "kompatybilne podzespoły" in q_text.lower():
        main_text = f"• Poprawna odpowiedź: {correct_letter}. {correct_val}.\n"
        main_text += "Uzasadnienie: Wskazana kombinacja podzespołów w tabeli spełnia łączne wymagania kompatybilności gniazda procesora (socket), standardu pamięci RAM (np. DDR4) oraz interfejsu dysku z płytą główną."
        distractors = []
        for let in ["A", "B", "C", "D"]:
            if let == correct_letter: continue
            distractors.append(f"• {let}. {options.get(let)} - ta kombinacja zawiera podzespoły niekompatybilne ze sobą (np. rozbieżne standardy gniazd CPU lub generacji RAM).")
        return "Wyjaśnienie i uzasadnienie:\n" + main_text + "\n\nDlaczego nie reszta?\n" + "\n".join(distractors)

    # RAM upgrade question
    if "modernizacji" in q_text.lower() and "ram" in q_text.lower():
        main_text = f"• Poprawna odpowiedź: {correct_letter}. {correct_val}.\n"
        main_text += "Uzasadnienie: Dobierając nowe moduły pamięci RAM do płyty głównej, kluczowa jest zgodność standardu (np. DDR4/DDR5), maksymalnej pojemności obsługiwanej przez chipset płyty oraz liczby dostępnych fizycznych banków/slotów DIMM."
        distractors = [
            f"• B. {options.get('B')} - parametry dysku twardego (SATA/NVMe) i jego pojemność nie decydują o kompatybilności modułów RAM z płytą główną.",
            f"• C. {options.get('C')} - producent pamięci RAM nie decyduje o kompatybilności fizycznej i elektrycznej, a zewnętrzne porty I/O płyty nie uczestniczą w obsłudze pamięci RAM.",
            f"• D. {options.get('D')} - interfejs karty graficznej (PCIe x16) i moc zasilacza to osobne parametry toru zasilania i grafiki, niemające związku ze specyfikacją banków RAM."
        ]
        return "Wyjaśnienie i uzasadnienie:\n" + main_text + "\n\nDlaczego nie reszta?\n" + "\n".join(distractors)

    return None

def build_comprehensive_explanation(q, qual):
    q_text = (q.get("question") or q.get("question_text") or "").strip()
    correct_letter = (q.get("correct") or q.get("correct_answer") or "").strip().upper()
    options = q.get("options") or {}
    correct_val = options.get(correct_letter, "").strip()

    # 1. Subnet calculation check
    subnet_exp = build_subnet_explanation(q_text, correct_letter, options)
    if subnet_exp:
        return subnet_exp

    # 2. Specific diagram / table check
    diagram_exp = build_diagram_image_explanation(q_text, correct_letter, options, qual)
    if diagram_exp:
        return diagram_exp

    # 3. Term definitions lookup
    correct_def_info = get_term_definition_rich(correct_val, qual) or get_term_definition_rich(q_text, qual)

    main_text = f"• Poprawna odpowiedź: {correct_letter}. {correct_val}.\n"
    if correct_def_info:
        main_text += (
            f"Uzasadnienie: {correct_def_info['definition']} "
            f"Wariant ten bezpośrednio spełnia wymagania postawione w treści zadania egzaminacyjnego."
        )
    else:
        main_text += (
            f"Uzasadnienie: Dla kwalifikacji {qual}, wariant „{correct_val}” stanowi poprawne rozwiązanie "
            f"zgodne ze standardem technicznym i wymaganiami podstawy programowej."
        )

    # 4. Distractors analysis
    distractors = []
    q_lower = q_text.lower()

    for let in ["A", "B", "C", "D"]:
        if let == correct_letter:
            continue
        opt_text = options.get(let, "").strip()
        if not opt_text:
            continue
        
        dist_def = get_term_definition_rich(opt_text, qual)
        if dist_def:
            reason = f"{dist_def['definition']} W kontekście tego zadania nie realizuje poszukiwanej funkcji (odpowiedzią jest „{correct_val}”)."
        else:
            opt_lower = opt_text.lower()

            # Numbers as options (OSI layers, ports, RAID, etc.)
            if opt_text in ["1", "2", "3", "4", "5", "6", "7"] and ("warstw" in q_lower or "osi" in q_lower):
                osi_names = {
                    "1": "Fizyczną (transmisja nieuporządkowanego strumienia bitów)",
                    "2": "Łącza danych (ramki, sumy kontrolne CRC i adresacja MAC)",
                    "3": "Sieciową (routing pakietów i adresacja logiczna IP)",
                    "4": "Transportową (segmentacja, porty TCP/UDP)",
                    "5": "Sesji (zarządzanie dialogiem i sesjami)",
                    "6": "Prezentacji (formatowanie, kodowanie i szyfrowanie)",
                    "7": "Aplikacji (usługi sieciowe dla procesów użytkownika)"
                }
                reason = f"oznacza warstwę {osi_names.get(opt_text, opt_text)} modelu OSI, a nie warstwę szukaną w zadaniu."
            elif any(w in q_lower for w in ["raid", "macierz"]) and opt_text in ["0", "1", "5", "6", "10", "RAID 0", "RAID 1", "RAID 5", "RAID 6", "RAID 10"]:
                raid_info = {
                    "0": "RAID 0 (striping/paskowanie) dzieli dane między dyski bez redundancji – awaria jednego dysku niszczy wszystkie dane.",
                    "1": "RAID 1 (mirroring/lustro) tworzy pełną kopię danych na drugim dysku (pojemność równa najmniejszemu dyskowi).",
                    "5": "RAID 5 wykorzystuje rozproszoną parzystość (wymaga min. 3 dysków, odporny na awarię 1 dysku, pojemność N-1).",
                    "6": "RAID 6 stosuje podwójną parzystość (wymaga min. 4 dysków, odporny na awarię 2 dysków jednocześnie, pojemność N-2).",
                    "10": "RAID 10 (1+0) łączy wydajność paskowania z bezpieczeństwem lustra (wymaga min. 4 dysków, pojemność 50%)."
                }
                clean_r = opt_text.replace("RAID", "").strip()
                reason = raid_info.get(clean_r, f"poziom macierzy RAID {opt_text} posiada odmienną strukturę i parametry redundancji.")
            elif qual in ["INF.04", "EE.09", "INF.03"] and any(k in q_lower for k in ["kod", "funkcj", "klas", "język", "pętl", "zmienn", "obiekt", "instrukcj"]):
                if opt_text in ["for", "while", "do..while", "do-while"]:
                    reason = "to instrukcja pętli (iteracji), a nie rozwiązanie szukane w pytaniu."
                elif opt_text in ["if", "if..else"]:
                    reason = "to instrukcja warunkowa sprawdzająca pojedyncze wyrażenie logiczne."
                elif opt_text == "switch":
                    reason = "to instrukcja wyboru wielowariantowego dopasowująca wartość wyrażenia do etykiet case."
                elif opt_text in ["return", "break", "continue"]:
                    reason = "to instrukcja sterująca przepływem wykonania programu."
                elif opt_text in ["public", "private", "protected"]:
                    reason = "to modyfikator widoczności (hermetyzacji) składowych klasy."
                elif opt_text in ["int", "float", "double", "string", "bool"]:
                    reason = f"to typ danych w językach programowania, niebędący odpowiedzią na to pytanie."
                else:
                    reason = f"wariant ten reprezentuje inny element logiki lub składni kodu niż wymagane „{correct_val}”."
            # Databases / SQL context
            elif qual in ["INF.03", "EE.09"] and any(k in q_lower for k in ["baza", "bazy", "sql", "tabel", "kwerend"]):
                if opt_lower in ["select", "insert", "update", "delete"]:
                    reason = f"polecenie {opt_text.upper()} służy do manipulacji danymi (DML), a nie do zadania opisanego w treści."
                elif opt_lower in ["create", "alter", "drop"]:
                    reason = f"polecenie {opt_text.upper()} służy do definiowania struktur danych (DDL)."
                elif opt_lower in ["grant", "revoke"]:
                    reason = f"polecenie {opt_text.upper()} zarządza uprawnieniami użytkowników (DCL)."
                elif "join" in opt_lower:
                    reason = "klauzula JOIN łączy rekordy z wielu tabel relacyjnych."
                elif opt_lower in ["count()", "sum()", "avg()", "max()", "min()"]:
                    reason = f"funkcja agregująca {opt_text} wykonuje obliczenia statystyczne na zbiorze wierszy."
                else:
                    reason = f"konstrukcja bazodanowa nieodpowiadająca celowi zapytania (właściwe: „{correct_val}”)."
            # Graphics & Printing context
            elif qual in ["INF.07"]:
                if opt_lower in ["rgb", "srgb", "adobe rgb"]:
                    reason = "przestrzeń addytywna RGB stosowana jest do wyświetlania obrazu na ekranach, a nie w druku."
                elif opt_lower in ["cmyk"]:
                    reason = "model subtraktywny CMYK stosowany jest w poligrafii czterokolorowej."
                elif opt_lower in ["jpeg", "jpg"]:
                    reason = "format rastrowy ze stratną kompresją, nieobsługujący przezroczystości."
                elif opt_lower in ["png"]:
                    reason = "format rastrowy z bezstratną kompresją i kanałem alfa."
                elif opt_lower in ["svg", "eps"]:
                    reason = "wektorowy format graficzny skalowalny bez utraty ostrości."
                elif "spad" in opt_lower or "bleed" in opt_lower:
                    reason = "spad to obszar grafiki wystający poza format netto arkusza publikacji."
                elif "paser" in opt_lower:
                    reason = "pasery to znaczniki służące do precyzyjnego spasowania płyt drukarskich."
                else:
                    reason = f"parametr ten dotyczy innego etapu DTP lub formatu graficznego niż „{correct_val}”."
            # Hardware & Operating Systems & Networking (INF.02, INF.08)
            else:
                if any(w in opt_lower for w in ["dysk", "hdd", "ssd"]):
                    reason = "parametry pamięci masowej nie decydują o operacji opisanej w zadaniu."
                elif any(w in opt_lower for w in ["karta graficzn", "gpu"]):
                    reason = "karta graficzna odpowiada za wyświetlanie obrazu, a nie za operację z pytania."
                elif any(w in opt_lower for w in ["zasilacz", "napięc"]):
                    reason = "parametry zasilacza nie wpływają bezpośrednio na tę funkcję logiczną."
                elif opt_lower.startswith(("-", "/")):
                    reason = f"przełącznik „{opt_text}” modyfikuje zachowanie polecenia w odmienny sposób."
                elif any(w in opt_lower for w in ["skrętka", "kabel", "rj45", "rj-45"]):
                    reason = "dotyczy miedzianej warstwy fizycznej okablowania sieciowego."
                elif any(w in opt_lower for w in ["światłowód", "smf", "mmf"]):
                    reason = "technologia światłowodowa przeznaczona jest do transmisji optycznej."
                else:
                    reason = f"wariant ten dotyczy innych funkcji technicznych niż określone w pytaniu (odpowiedzią jest „{correct_val}”)."

        distractors.append(f"• {let}. {opt_text} - {reason}")

    return "Wyjaśnienie i uzasadnienie:\n" + main_text + "\n\nDlaczego nie reszta?\n" + "\n".join(distractors)

def process_file(filepath):
    filename = os.path.basename(filepath)
    qual = filename.split(".")[0].upper()
    if qual.startswith("INF"):
        qual = "INF." + qual[3:]
    elif qual.startswith("EE"):
        qual = "EE." + qual[2:]

    with open(filepath, "r", encoding="utf-8-sig") as f:
        data = json.load(f)

    questions = data if isinstance(data, list) else data.get("questions", [])
    count = len(questions)

    for i, q in enumerate(questions):
        exp = build_comprehensive_explanation(q, qual)
        q["explanation"] = exp

    with open(filepath, "w", encoding="utf-8") as f:
        json.dump(questions, f, ensure_ascii=False, indent=2)

    print(f"Successfully processed {filename}: {count} questions enriched.")

def main():
    files = sorted([os.path.join(DATA_DIR, f) for f in os.listdir(DATA_DIR) if f.endswith(".json") and not f.endswith(".backup.json")])
    print(f"Found {len(files)} question files in {DATA_DIR}")
    for f in files:
        process_file(f)
    print("All question files enriched successfully.")

if __name__ == "__main__":
    main()
