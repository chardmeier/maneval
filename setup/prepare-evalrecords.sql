begin;

delete from tasks;
delete from judgments;

-- General corpora:
-- in each set:
--   320 unique lines
--    80 lines shared with the other set
--    20 lines duplicated

create temp table general_set (item integer primary key autoincrement, set_id text, line integer);

-- IAA set first

insert into general_set (set_id, line)
select distinct 'A', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='general/source.txt'
order by random()
limit 80;

insert into general_set (set_id, line)
select 'B', line from general_set;

-- distinct examples

insert into general_set (set_id, line)
select distinct 'A', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='general/source.txt'
and line not in (select line from general_set)
order by random()
limit 320;

insert into general_set (set_id, line)
select distinct 'B', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='general/source.txt'
and line not in (select line from general_set)
order by random()
limit 320;

-- intra-annotator agreement

insert into general_set (set_id, line)
select set_id, line from general_set
where set_id='A'
order by random()
limit 20;

insert into general_set (set_id, line)
select set_id, line from general_set
where set_id='B'
order by random()
limit 20;

-- Discourse corpora:
-- in each set:
--   80 unique lines
--   20 lines shared with the other set
--   10 lines duplicated

create temp table discourse_set (item integer primary key autoincrement, set_id text, line integer);

-- IAA set first

insert into discourse_set (set_id, line)
select distinct 'A', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='discourse/source.txt'
order by random()
limit 20;

insert into discourse_set (set_id, line)
select 'B', line from discourse_set;

-- distinct examples

insert into discourse_set (set_id, line)
select distinct 'A', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='discourse/source.txt'
and line not in (select line from discourse_set)
order by random()
limit 80;

insert into discourse_set (set_id, line)
select distinct 'B', line from sentences, corpora
where sentences.corpus=corpora.id
and corpora.name='discourse/source.txt'
and line not in (select line from discourse_set)
order by random()
limit 80;

-- intra-annotator agreement

insert into discourse_set (set_id, line)
select set_id, line from discourse_set
where set_id='A'
order by random()
limit 10;

insert into discourse_set (set_id, line)
select set_id, line from discourse_set
where set_id='B'
order by random()
limit 10;

-- Merge sets

create temp table all_sets (item integer primary key autoincrement, set_id text, line integer);

insert into all_sets (set_id, line)
select set_id, line from general_set;

insert into all_sets (set_id, line)
select set_id, line from discourse_set;

-- Create task records

-- Annotator 1

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 1, src.id, 'Fluency', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='general/source.txt' and
baseline.name='general/baseline.txt' and
docrepair.name='general/docrepair.txt' and
transference.name='general/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 2, src.id, 'Adequacy', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='general/source.txt' and
baseline.name='general/baseline.txt' and
docrepair.name='general/docrepair.txt' and
transference.name='general/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 3, src.id, 'Fluency', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='discourse/source.txt' and
baseline.name='discourse/baseline.txt' and
docrepair.name='discourse/docrepair.txt' and
transference.name='discourse/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 4, src.id, 'Adequacy', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='discourse/source.txt' and
baseline.name='discourse/baseline.txt' and
docrepair.name='discourse/docrepair.txt' and
transference.name='discourse/transference.txt';

-- Annotator 2 - has adequacy and fluency swapped

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 5, src.id, 'Adequacy', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='general/source.txt' and
baseline.name='general/baseline.txt' and
docrepair.name='general/docrepair.txt' and
transference.name='general/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 6, src.id, 'Fluency', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='general/source.txt' and
baseline.name='general/baseline.txt' and
docrepair.name='general/docrepair.txt' and
transference.name='general/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 7, src.id, 'Adequacy', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='discourse/source.txt' and
baseline.name='discourse/baseline.txt' and
docrepair.name='discourse/docrepair.txt' and
transference.name='discourse/transference.txt';

insert into tasks (id, source, eval_type, corpus1, corpus2, corpus3)
select 8, src.id, 'Fluency', baseline.id, docrepair.id, transference.id
from corpora as src, corpora as baseline, corpora as docrepair, corpora as transference
where src.name='discourse/source.txt' and
baseline.name='discourse/baseline.txt' and
docrepair.name='discourse/docrepair.txt' and
transference.name='discourse/transference.txt';
-- Create judgement records

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus2, line
from tasks, general_set
where tasks.id=1;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus2, corpus3, line
from tasks, general_set
where tasks.id=1;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus3, line
from tasks, general_set
where tasks.id=1;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus2, line
from tasks, general_set
where tasks.id=2;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus2, corpus3, line
from tasks, general_set
where tasks.id=2;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus3, line
from tasks, general_set
where tasks.id=2;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus2, line
from tasks, discourse_set
where tasks.id=3;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus2, corpus3, line
from tasks, discourse_set
where tasks.id=3;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus3, line
from tasks, discourse_set
where tasks.id=3;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus2, line
from tasks, discourse_set
where tasks.id=4;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus2, corpus3, line
from tasks, discourse_set
where tasks.id=4;

insert into judgments (task_id, item, corpus1, corpus2, line)
select tasks.id, item, corpus1, corpus3, line
from tasks, discourse_set
where tasks.id=4;

-- Judgements for the second annotator are the same, except for the
-- swapping of adequacy and fluency in the task description.

insert into judgments (task_id, item, corpus1, corpus2, line)
select task_id+4, item, corpus1, corpus2, line
from judgments;

drop table general_set;
drop table discourse_set;

commit;

