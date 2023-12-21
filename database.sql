DROP TABLE IF EXISTS urls;
DROP TABLE IF EXISTS url_checks;

CREATE TABLE urls (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name        varchar(255),
    created_at  timestamp
);

CREATE TABLE url_checks (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    url_id       bigint REFERENCES urls (id),
    status_code varchar(255),
    h1            varchar(255),
    title         varchar(255),
    description   varchar(600),
    created_at    timestamp
);