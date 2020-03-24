drop table current_task;
create table current_task (
	key text primary key,
	task integer
);

drop table tasks;
create table tasks (
	id integer primary key,
	source integer not null,
	eval_type text not null,
	corpus1 integer not null,
	corpus2 integer not null,
	corpus3 integer not null
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
	sentence text not null,
	orderid integer not null
);
create index sentences_corpusline on sentences (corpus, line);

drop table judgments;
create table judgments (
	id integer primary key autoincrement,
	task_id integer not null,
	corpus1 integer not null,
	corpus2 integer not null,
	line integer not null,
	judgment integer
);
create index judgments_c1c2taskline on judgments (corpus1, corpus2, task_id, line);
