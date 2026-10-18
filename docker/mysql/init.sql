-- Rulează o singură dată, la crearea volumului. Baza separată pentru testele Pest și drepturi pe bazele
-- create de testele paralele (creative_loyalty_test_1, _2, ...).
CREATE DATABASE IF NOT EXISTS creative_loyalty_test;
GRANT ALL PRIVILEGES ON `creative\_loyalty%`.* TO 'loyalty'@'%';
FLUSH PRIVILEGES;
