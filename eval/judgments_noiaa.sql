begin;

drop table if exists judgments_noiaa;
create table judgments_noiaa as select * from judgments;

-- Intra-annotator examples

drop table if exists deleted_judgments_intra;
create table deleted_judgments_intra (id integer);

-- delete examples with conflicting annotations (note j1.item!=j2.item)

insert into deleted_judgments_intra
select j1.id
from judgments_noiaa as j1,
     judgments_noiaa as j2
where j1.corpus1 = j2.corpus1
  and j1.corpus2 = j2.corpus2
  and j1.line = j2.line
  and j1.task_id = j2.task_id
  and j1.item != j2.item
  and j1.judgment != j2.judgment;

-- if annotations agree, delete only one of them (note j1.item<j2.item)

insert into deleted_judgments_intra
select j2.id
from judgments_noiaa as j1,
     judgments_noiaa as j2
where j1.corpus1 = j2.corpus1
  and j1.corpus2 = j2.corpus2
  and j1.line = j2.line
  and j1.task_id = j2.task_id
  and j1.item < j2.item
  and j1.judgment = j2.judgment;

delete from judgments_noiaa where id in (select id from deleted_judgments_intra);

-- Inter-annotation examples

drop table if exists deleted_judgments_inter;
create table deleted_judgments_inter (id integer);

-- delete examples with conflicting annotations (note j1.task_id!=j2.task_id)

insert into deleted_judgments_inter
select j1.id
from judgments_noiaa as j1,
     judgments_noiaa as j2,
     tasks as t1,
     tasks as t2
where j1.task_id = t1.id
  and j2.task_id = t2.id
  and j1.corpus1 = j2.corpus1
  and j1.corpus2 = j2.corpus2
  and j1.line = j2.line
  and j1.task_id != j2.task_id
  and t1.eval_type = t2.eval_type
  and t1.source = t2.source
  and j1.judgment != j2.judgment;

-- if annotations agree, delete only one of them (note j1.task_id<j2.task_id)

insert into deleted_judgments_inter
select j2.id
from judgments_noiaa as j1,
     judgments_noiaa as j2,
     tasks as t1,
     tasks as t2
where j1.task_id = t1.id
  and j2.task_id = t2.id
  and j1.corpus1 = j2.corpus1
  and j1.corpus2 = j2.corpus2
  and j1.line = j2.line
  and j1.task_id < j2.task_id
  and t1.eval_type = t2.eval_type
  and t1.source = t2.source
  and j1.judgment = j2.judgment;

delete from judgments_noiaa where id in (select id from deleted_judgments_inter);

commit;
