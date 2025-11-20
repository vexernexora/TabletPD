-- Struktura bazy danych dla systemu wyroków
-- Enhanced verdict system database structure

-- Tabela obywateli
CREATE TABLE IF NOT EXISTS `obywatele` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `imie` varchar(50) NOT NULL,
  `nazwisko` varchar(50) NOT NULL,
  `pesel` varchar(11) NOT NULL UNIQUE,
  `data_urodzenia` date NOT NULL,
  `miejsce_urodzenia` varchar(100),
  `adres` text,
  `telefon` varchar(20),
  `email` varchar(100),
  `dowod_osobisty` varchar(20),
  `prawo_jazdy` varchar(20),
  `status_karalnosci` enum('KARANY','NIE_KARANY') DEFAULT 'NIE_KARANY',
  `notatki` text,
  `data_utworzenia` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_aktualizacji` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pesel` (`pesel`),
  INDEX `idx_nazwisko` (`nazwisko`),
  INDEX `idx_status` (`status_karalnosci`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela kategorii wykroczeń
CREATE TABLE IF NOT EXISTS `kategorie_wykroczen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazwa` varchar(50) NOT NULL,
  `opis` text,
  `kolor` varchar(7) DEFAULT '#3b82f6',
  `ikona` varchar(50),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dodaj domyślne kategorie wykroczeń
INSERT IGNORE INTO `kategorie_wykroczen` (`nazwa`, `opis`, `kolor`, `ikona`) VALUES
('traffic', 'Wykroczenia drogowe', '#f59e0b', 'car'),
('violent', 'Przestępstwa związane z przemocą', '#ef4444', 'warning'),
('property', 'Przestępstwa przeciwko mieniu', '#8b5cf6', 'home'),
('other', 'Inne wykroczenia', '#64748b', 'more');

-- Ulepszona tabela wykroczeń
CREATE TABLE IF NOT EXISTS `wykroczenia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazwa` varchar(200) NOT NULL,
  `opis` text,
  `kwota` decimal(10,2) NOT NULL,
  `kategoria` varchar(50) DEFAULT 'other',
  `kodeks_artykul` varchar(100),
  `punkty_karne` int(3) DEFAULT 0,
  `mozliwe_aresztowanie` boolean DEFAULT FALSE,
  `min_kwota` decimal(10,2),
  `max_kwota` decimal(10,2),
  `aktywne` boolean DEFAULT TRUE,
  `data_utworzenia` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_aktualizacji` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_kategoria` (`kategoria`),
  INDEX `idx_aktywne` (`aktywne`),
  FOREIGN KEY (`kategoria`) REFERENCES `kategorie_wykroczen`(`nazwa`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przykładowe wykroczenia
INSERT IGNORE INTO `wykroczenia` (`nazwa`, `opis`, `kwota`, `kategoria`, `kodeks_artykul`, `punkty_karne`) VALUES
-- Wykroczenia drogowe
('Przekroczenie prędkości o 10-20 km/h', 'Przekroczenie dozwolonej prędkości w terenie zabudowanym', 100.00, 'traffic', 'Art. 92 uokik', 1),
('Przekroczenie prędkości o 21-30 km/h', 'Przekroczenie dozwolonej prędkości w terenie zabudowanym', 200.00, 'traffic', 'Art. 92 uokik', 3),
('Przekroczenie prędkości o 31-40 km/h', 'Przekroczenie dozwolonej prędkości w terenie zabudowanym', 400.00, 'traffic', 'Art. 92 uokik', 6),
('Przekroczenie prędkości powyżej 50 km/h', 'Znaczne przekroczenie dozwolonej prędkości', 1000.00, 'traffic', 'Art. 92 uokik', 10),
('Parkowanie w miejscu zabronionym', 'Nieprawidłowe parkowanie pojazdu', 100.00, 'traffic', 'Art. 49 uokik', 0),
('Przejazd na czerwonym świetle', 'Nieprzestrzeganie sygnalizacji świetlnej', 500.00, 'traffic', 'Art. 97 uokik', 6),
('Jazda bez prawa jazdy', 'Prowadzenie pojazdu bez uprawnień', 1500.00, 'traffic', 'Art. 94 uokik', 10),
('Jazda po alkoholu (0.2-0.5‰)', 'Prowadzenie pojazdu w stanie po użyciu alkoholu', 2500.00, 'traffic', 'Art. 87 kw', 10),
('Niezapięte pasy bezpieczeństwa', 'Niezastosowanie pasów bezpieczeństwa', 100.00, 'traffic', 'Art. 39 uokik', 1),
('Używanie telefonu podczas jazdy', 'Korzystanie z telefonu bez zestawu głośnomówiącego', 200.00, 'traffic', 'Art. 45 uokik', 2),

-- Przestępstwa przeciwko mieniu
('Kradzież', 'Zabór cudzej rzeczy ruchomej', 500.00, 'property', 'Art. 278 kk', 0),
('Kradzież z włamaniem', 'Kradzież poprzez włamanie się', 2000.00, 'property', 'Art. 279 kk', 0),
('Uszkodzenie mienia', 'Zniszczenie lub uszkodzenie cudzej rzeczy', 300.00, 'property', 'Art. 288 kk', 0),
('Wandalizm', 'Niszczenie lub uszkadzanie mienia publicznego', 800.00, 'property', 'Art. 288 kk', 0),
('Graffiti', 'Umieszczanie napisów lub rysunków w miejscach niedozwolonych', 500.00, 'property', 'Art. 63a kw', 0),

-- Przestępstwa związane z przemocą
('Pobicie', 'Naruszenie nietykalności cielesnej', 1000.00, 'violent', 'Art. 217 kk', 0),
('Groźby karalne', 'Grożenie popełnieniem przestępstwa', 500.00, 'violent', 'Art. 190 kk', 0),
('Zakłócanie porządku publicznego', 'Działania zakłócające spokój publiczny', 200.00, 'violent', 'Art. 51 kw', 0),
('Znieważenie funkcjonariusza', 'Znieważanie osoby pełniącej funkcję publiczną', 1500.00, 'violent', 'Art. 226 kk', 0),

-- Inne wykroczenia
('Zakłócanie ciszy nocnej', 'Powodowanie hałasu w porze nocnej', 100.00, 'other', 'Art. 51 kw', 0),
('Spożywanie alkoholu w miejscu publicznym', 'Picie alkoholu w niedozwolonym miejscu', 100.00, 'other', 'Art. 43 kw', 0),
('Zanieczyszczanie środowiska', 'Wyrzucanie śmieci w miejscach niedozwolonych', 200.00, 'other', 'Art. 145 kw', 0),
('Nielegalne handlowanie', 'Prowadzenie działalności handlowej bez zezwolenia', 1000.00, 'other', 'Art. 122 kw', 0),
('Zakłócanie funkcjonowania instytucji', 'Utrudnianie działania urzędów lub służb', 500.00, 'other', 'Art. 54 kw', 0);

-- Ulepszona tabela historii aktywności
CREATE TABLE IF NOT EXISTS `historia_aktywnosci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `obywatel_id` int(11) NOT NULL,
  `typ` enum('mandat','aresztowanie','notatka','log_system','ostrzezenie') NOT NULL,
  `opis` text NOT NULL,
  `kwota` decimal(10,2) DEFAULT NULL,
  `funkcjonariusz` varchar(100),
  `nr_odznaki` varchar(20),
  `miejsce` varchar(200),
  `data` timestamp DEFAULT CURRENT_TIMESTAMP,
  `data_zakonczenia` timestamp NULL,
  `status` enum('aktywny','zakonczony','anulowany') DEFAULT 'aktywny',
  `priorytet` enum('niski','normalny','wysoki','krytyczny') DEFAULT 'normalny',
  `kategoria_id` int(11) NULL,
  `wykroczenie_id` int(11) NULL,
  `punkty_karne` int(3) DEFAULT 0,
  `dodatkowe_dane` JSON,
  `ip_adres` varchar(45),
  `user_agent` text,
  PRIMARY KEY (`id`),
  INDEX `idx_obywatel` (`obywatel_id`),
  INDEX `idx_typ` (`typ`),
  INDEX `idx_data` (`data`),
  INDEX `idx_funkcjonariusz` (`funkcjonariusz`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`obywatel_id`) REFERENCES `obywatele`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`wykroczenie_id`) REFERENCES `wykroczenia`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela logów systemowych
CREATE TABLE IF NOT EXISTS `logi_systemowe` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ_akcji` varchar(50) NOT NULL,
  `opis` text NOT NULL,
  `uzytkownik_id` int(11),
  `uzytkownik_nazwa` varchar(100),
  `adres_ip` varchar(45),
  `user_agent` text,
  `dane_przed` JSON,
  `dane_po` JSON,
  `data_utworzenia` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_typ_akcji` (`typ_akcji`),
  INDEX `idx_uzytkownik` (`uzytkownik_id`),
  INDEX `idx_data` (`data_utworzenia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela statystyk
CREATE TABLE IF NOT EXISTS `statystyki` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `typ` varchar(50) NOT NULL,
  `wartosc` bigint NOT NULL DEFAULT 0,
  `data_obliczenia` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dodatkowe_info` JSON,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_typ` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widok statystyk obywateli
CREATE OR REPLACE VIEW `statystyki_obywateli` AS
SELECT 
  COUNT(*) as liczba_obywateli,
  COUNT(CASE WHEN status_karalnosci = 'KARANY' THEN 1 END) as liczba_karanych,
  COUNT(CASE WHEN status_karalnosci = 'NIE_KARANY' THEN 1 END) as liczba_niekaranych,
  ROUND(COUNT(CASE WHEN status_karalnosci = 'KARANY' THEN 1 END) * 100.0 / COUNT(*), 2) as procent_karanych
FROM obywatele;

-- Widok statystyk mandatów
CREATE OR REPLACE VIEW `statystyki_mandatow` AS
SELECT 
  COUNT(*) as liczba_mandatow,
  COALESCE(SUM(kwota), 0) as suma_mandatow,
  COALESCE(AVG(kwota), 0) as srednia_kwota,
  COUNT(DISTINCT obywatel_id) as liczba_ukaranych_osob,
  COUNT(DISTINCT funkcjonariusz) as liczba_funkcjonariuszy
FROM historia_aktywnosci 
WHERE typ = 'mandat';

-- Procedura do aktualizacji statystyk
DELIMITER //
CREATE OR REPLACE PROCEDURE `aktualizuj_statystyki`()
BEGIN
  -- Aktualizuj podstawowe statystyki
  INSERT INTO statystyki (typ, wartosc) VALUES ('liczba_obywateli', (SELECT COUNT(*) FROM obywatele))
  ON DUPLICATE KEY UPDATE wartosc = (SELECT COUNT(*) FROM obywatele);
  
  INSERT INTO statystyki (typ, wartosc) VALUES ('liczba_mandatow', (SELECT COUNT(*) FROM historia_aktywnosci WHERE typ = 'mandat'))
  ON DUPLICATE KEY UPDATE wartosc = (SELECT COUNT(*) FROM historia_aktywnosci WHERE typ = 'mandat');
  
  INSERT INTO statystyki (typ, wartosc) VALUES ('suma_mandatow', (SELECT COALESCE(SUM(kwota), 0) FROM historia_aktywnosci WHERE typ = 'mandat'))
  ON DUPLICATE KEY UPDATE wartosc = (SELECT COALESCE(SUM(kwota), 0) FROM historia_aktywnosci WHERE typ = 'mandat');
  
  INSERT INTO statystyki (typ, wartosc) VALUES ('liczba_aresztowan', (SELECT COUNT(*) FROM historia_aktywnosci WHERE typ = 'aresztowanie'))
  ON DUPLICATE KEY UPDATE wartosc = (SELECT COUNT(*) FROM historia_aktywnosci WHERE typ = 'aresztowanie');
END //
DELIMITER ;

-- Trigger do automatycznej aktualizacji statystyk
DELIMITER //
CREATE OR REPLACE TRIGGER `trigger_aktualizuj_statystyki_po_mandacie`
AFTER INSERT ON `historia_aktywnosci`
FOR EACH ROW
BEGIN
  IF NEW.typ IN ('mandat', 'aresztowanie') THEN
    CALL aktualizuj_statystyki();
  END IF;
END //
DELIMITER ;

-- Trigger do aktualizacji statusu obywatela
DELIMITER //
CREATE OR REPLACE TRIGGER `trigger_aktualizuj_status_obywatela`
AFTER INSERT ON `historia_aktywnosci`
FOR EACH ROW
BEGIN
  IF NEW.typ IN ('mandat', 'aresztowanie') THEN
    UPDATE obywatele 
    SET status_karalnosci = 'KARANY', 
        data_aktualizacji = CURRENT_TIMESTAMP
    WHERE id = NEW.obywatel_id;
  END IF;
END //
DELIMITER ;

-- Indeksy dla lepszej wydajności
CREATE INDEX IF NOT EXISTS `idx_historia_obywatel_typ` ON `historia_aktywnosci` (`obywatel_id`, `typ`);
CREATE INDEX IF NOT EXISTS `idx_historia_data_typ` ON `historia_aktywnosci` (`data`, `typ`);
CREATE INDEX IF NOT EXISTS `idx_obywatele_status_nazwisko` ON `obywatele` (`status_karalnosci`, `nazwisko`);

-- Funkcja do wyszukiwania obywateli
DELIMITER //
CREATE OR REPLACE FUNCTION `wyszukaj_obywateli`(szukany_tekst VARCHAR(255))
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
  DECLARE wynik TEXT;
  
  SELECT GROUP_CONCAT(
    CONCAT(id, ':', imie, ' ', nazwisko) 
    SEPARATOR ';'
  ) INTO wynik
  FROM obywatele 
  WHERE imie LIKE CONCAT('%', szukany_tekst, '%')
     OR nazwisko LIKE CONCAT('%', szukany_tekst, '%')
     OR pesel LIKE CONCAT('%', szukany_tekst, '%')
     OR dowod_osobisty LIKE CONCAT('%', szukany_tekst, '%')
  ORDER BY nazwisko, imie
  LIMIT 10;
  
  RETURN COALESCE(wynik, '');
END //
DELIMITER ;

-- Aktualiza istniejące statystyki
CALL aktualizuj_statystyki();

-- Komentarze dla dokumentacji
ALTER TABLE `obywatele` COMMENT = 'Tabela przechowująca dane osobowe obywateli';
ALTER TABLE `wykroczenia` COMMENT = 'Lista wykroczeń i ich parametrów';
ALTER TABLE `historia_aktywnosci` COMMENT = 'Historia wszystkich kontaktów z obywatelami';
ALTER TABLE `logi_systemowe` COMMENT = 'Logi działań systemowych dla audytu';
ALTER TABLE `statystyki` COMMENT = 'Statystyki systemowe aktualizowane automatycznie';

-- Uprawnienia dla użytkowników (przykład)
-- GRANT SELECT, INSERT, UPDATE ON obywatele TO 'funkcjonariusz'@'%';
-- GRANT SELECT, INSERT ON historia_aktywnosci TO 'funkcjonariusz'@'%';
-- GRANT SELECT ON wykroczenia TO 'funkcjonariusz'@'%';
-- GRANT ALL PRIVILEGES ON *.* TO 'admin'@'%';

COMMIT;
