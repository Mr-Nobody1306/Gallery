create schema if not exists gallery;
create table gallery.users (
    id serial primary key,
    username varchar(50) unique not null,
    email varchar(100) unique not null,
    pwd varchar(255) not null,
    created_at timestamptz default now()
);

create table gallery.album (
    id serial primary key,
    user_id integer not null references gallery.users(id) on delete cascade,
    title varchar(150) not null,
    album_description text,
    is_public boolean default true,
    created_at timestamptz default now()
);

create table gallery.photos (
    id serial primary key,
    album_id integer not null references gallery.album(id) on delete cascade,
    user_id integer not null references gallery.users(id) on delete cascade,
    title varchar(150) not null,
    photo_description text,
    filename varchar(255) not null,
    url varchar(500) not null,
    thumbnail varchar(500) not null,
    width integer,
    height integer,
    is_public boolean default true,
    uploaded_at timestamptz default now()
);

create table gallery.photo_likes (
    id serial primary key,
    photo_id integer not null references gallery.photos(id) on delete cascade,
    user_id integer not null references gallery.users(id) on delete cascade,
    created_at timestamptz default now(),
    unique (photo_id, user_id)
);