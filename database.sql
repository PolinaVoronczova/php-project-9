DROP TABLE IF EXISTS sites;
DROP TABLE IF EXISTS cheack_history;

CREATE TABLE sites (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name        varchar(255),
    created_at  timestamp
);

CREATE TABLE cheack_history (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    site_id       bigint REFERENCES sites (id),
    response_code varchar(255),
    h1            varchar(255),
    title         varchar(255),
    description   varchar(255),
    created_at    timestamp
);