SELECT 'CREATE DATABASE wallet_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wallet_testing')\gexec
