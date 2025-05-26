-- Grant all privileges to kenesfood user
GRANT ALL PRIVILEGES ON *.* TO 'kenesfood'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON information_schema.* TO 'kenesfood'@'%';
FLUSH PRIVILEGES;