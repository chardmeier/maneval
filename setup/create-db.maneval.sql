drop table current_task;
create table current_task (
	task integer
);

drop table tournament;
create table tournament (
	id integer primary key,
	source integer not null,
	corpus1 integer not null,
	corpus2 integer not null,
	next1 integer,
	next2 integer
);

drop table corpora;
create table corpora (
	id integer primary key autoincrement,
	name text not null,
	srctgt integer not null
);

drop table sentences;
create table sentences (
	id integer primary key autoincrement,
	corpus integer not null,
	line integer not null,
	sentence text not null
);
create index sentences_corpusline on sentences (corpus, line);

drop table judgments;
create table judgments (
	id integer primary key autoincrement,
	corpus1 integer not null,
	corpus2 integer not null,
	line integer not null,
	judgment integer
);
create index judgments_c1c2line on judgments (corpus1, corpus2, line);

drop table documents;
create table documents (
	id integer primary key autoincrement,
	corpus integer not null,
	start integer not null,
	end integer not null
);
