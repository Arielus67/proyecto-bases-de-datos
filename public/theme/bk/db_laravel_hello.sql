drop database if exists db_laravel_hello;
create database db_laravel_hello;
use db_laravel_hello;

create table personas(
	id_persona int auto_increment primary key,
    nombre varchar(300),
    f_nacimiento date,
    genero char
);

create table comentarios(
	id_comentario int auto_increment primary key,
    id_persona int,
    texto text,
    fecha datetime default current_timestamp,
    foreign key (id_persona) references personas (id_persona) on update cascade on delete cascade
);


insert into personas (nombre, f_nacimiento, genero) values 
('Ana Moraga', '1995-02-25', 'f'),
('Luis Jara', '1987-01-03', 'm'),
('María Mora', '1995-08-12', 'f'),
('Javier Fuentes', '2000-10-21', 'm')
;

insert into comentarios (id_persona, texto) values 

(1, 'Lorem ipsum dolor sit amet 1'),
(1, 'Lorem ipsum dolor sit amet 2'),

(2, 'Lorem ipsum dolor sit amet 3'),
(2, 'Lorem ipsum dolor sit amet 4'),
(2, 'Lorem ipsum dolor sit amet 5'),

(3, 'Lorem ipsum dolor sit amet 6'),

(4, 'Lorem ipsum dolor sit amet 7'),
(4, 'Lorem ipsum dolor sit amet 8')
;