DROP TABLE IF EXISTS `races`;
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

INSERT INTO races (code, name, description, playable, hidden, portraitNextNumber, avatarNextNumber) VALUES
('OLYMP', 'olympien','De loin, on ne peut les différencier des Humains de la Terre mais de près on se rend compte qu\'ils sont bien plus massifs et résistants que ces derniers. Leurs côtes sont remplacées par une plaque de cartilage couvrant complètement la poitrine et le ventre et leurs yeux sont la plupart du temps orange. Leurs compétences sont très équilibrées. On dit qu\'un bataillon d\'Olympiens bien dirigé écrase les troupes de n\'importe quelle autre race.', TRUE, FALSE, 48, 147),
('GEANT', 'geant', 'Les géants possèdent une force hallucinante qui accompagne leur grande taille. Habitués à chasser dans les montagnes, ils sont également très doués au tir, mais peu habitués aux combats à grande échelle. Il est bien connu que ces colosses font souvent des cibles faciles et il est dangereux pour eux de trop s\'exposer sur les fronts.', TRUE, FALSE, 44, 94),
('NAIN', 'nain', 'De très petite taille, les Nains sont presque aussi larges que hauts. Leur barbe toujours bien entretenue est leur fierté mais aussi un bon moyen de les reconnaître. Les nains vivent dans des cités souterraines et sont de bons armuriers et inventeurs. Terriblement efficaces en combat rapproché, ils résistent correctement aux tirs et un peu moins bien à la magie. Leur lenteur massive reste leur défaut principal.', TRUE, FALSE, 51, 113),
('ELFE', 'elfe', 'C\'est une race élancée aux allures nobles. Ils existaient avant les Olympiens et ne vénèrent pas les Dieux de l\'Olympe. Les Elfes sont des créatures vivant dans les forêts et qui n\'en sortent que rarement. Doués en magie et plutôt bons en tir, les Elfes sont cependant de biens piètres guerriers de mêlée.', TRUE, FALSE, 34, 103),
('HS', 'hs', 'Leur stature très fine presque fragile leur confère une agilité hors du commun. Ils sont très forts en magie et excessivement difficiles à atteindre, mais qu\'ils soient blessés et leurs battements de cœur sont comptés… ', TRUE, FALSE, 43, 110);