CREATE TABLE races (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    playable BOOLEAN,
    hidden BOOLEAN,
    portraitNextNumber INT,
    avatarNextNumber INT

);
