create table users(
    id int primary key auto_increment,
    username varchar(50) unique not null,
    email varchar(100) unique not null,
    pwd varchar(255) not null,
    created_at timestamp default current_timestamp
)

create table album(
    id int primary key auto_increment,
    user_id int not null,
    title varchar(150) not null,
    album_description text,
    is_public boolean default true,
    created_at timestamp default current_timestamp,
    foreign key (user_id) references users(id) on delete cascade
)

create table photos(
    id int primary key auto_increment,
    album_id int not null,
    user_id int not null,
    title varchar(150) not null,
    photo_description text,
    filename varchar(255) not null,
    url varchar(500) not null,
    thumbnail varchar(500) not null,
    width int,
    height int,
    is_public boolean default true,
    uploaded_at timestamp default current_timestamp,
    foreign key (album_id) references album(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade
)

create table photo_likes(
    id int primary key auto_increment,
    photo_id int not null,
    user_id int not null,
    created_at timestamp default current_timestamp,
    foreign key (photo_id) references photos(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade,
    unique key unique_photo_user (photo_id, user_id)
)