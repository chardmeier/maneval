import numpy
import pandas
import scipy.stats
import sqlite3

from nltk.metrics.agreement import AnnotationTask


def main():
    dbfile = '/Users/christianhardmeier/Documents/project/2020-Chaojun/maneval-complete.db'
    db = sqlite3.connect(dbfile)

    pandas.set_option('display.width', 500)
    pandas.set_option('display.max_columns', None)

    report_intra_annotator_agreement(db)
    print()
    print()

    report_inter_annotator_agreement(db)
    print()
    print()

    report_system_comparison(db)


def report_intra_annotator_agreement(db):
    print('INTRA-ANNOTATOR AGREEMENT')
    print('=========================')
    print()
    print('Agreement per task:')
    print()

    cur = db.cursor()
    cur.execute('''select j1.task_id as task, j1.judgment as jg1, j2.judgment as jg2, count(*) as cnt
                from judgments as j1, judgments as j2
                where j1.corpus1=j2.corpus1 and j1.corpus2=j2.corpus2
                and j1.line=j2.line and j1.task_id=j2.task_id and j1.item<j2.item
                group by task, jg1, jg2 order by task, jg1, jg2''')
    data = cur.fetchall()
    res = pandas.DataFrame(data, columns=['task', 'jg1', 'jg2', 'cnt'], dtype=numpy.int)

    report_per_task(cur, res)

    print()
    print('Agreement per annotator:')
    print('Annotator 1 (Tasks 1-4)')
    report_agreement(res[res['task'] <= 4])
    print()
    print('Annotator 2 (Tasks 5-8)')
    report_agreement(res[res['task'] >= 5])


def report_inter_annotator_agreement(db):
    print('INTER-ANNOTATOR AGREEMENT')
    print('=========================')
    print()

    cur = db.cursor()
    cur.execute('''select t1.eval_type, src.name, t1.id, t2.id
                from tasks as t1, tasks as t2, corpora as src
                where t1.source=t2.source and t1.eval_type=t2.eval_type
                and src.id=t1.source and t1.id<t2.id''')
    paired_tasks = cur.fetchall()

    cur.execute("select task_id, corpus1 || '-' ||  corpus2 || '-' || line, judgment from judgments_nointra")
    res = pandas.DataFrame(cur.fetchall(), columns=['task', 'exid', 'judgment'])

    for eval_type, corpus, task1, task2 in paired_tasks:
        subset1 = res[res['task'] == task1]
        subset2 = res[res['task'] == task2]
        iaa_exid = set(subset1['exid']).intersection(subset2['exid'])
        iaa_data = list(subset1[subset1['exid'].isin(iaa_exid)].itertuples(index=False, name=None))
        iaa_data.extend(subset2[subset2['exid'].isin(iaa_exid)].itertuples(index=False, name=None))
        agr = AnnotationTask(iaa_data)
        print('%s %s Ao=%g kappa=%g alpha=%g pi=%g' % (eval_type, corpus, agr.Ao(task1, task2), agr.kappa(), agr.alpha(), agr.pi()))


def report_agreement(subres):
    total = subres['cnt'].sum()
    match = subres[subres['jg1'] == subres['jg2']]['cnt'].sum()
    print('Match: %d/%d = %g' % (match, total, match / total))


def report_per_task(cur, res, max_tasks=None):
    cur.execute('''select tasks.id, tasks.eval_type, src.name, c1.name, c2.name, c3.name 
                from tasks, corpora as src, corpora as c1, corpora as c2, corpora as c3
                where tasks.source=src.id and tasks.corpus1=c1.id 
                and tasks.corpus2=c2.id and tasks.corpus3=c3.id
                order by tasks.id''')
    for task, eval_type, src, c1, c2, c3 in cur.fetchall():
        if max_tasks and task > max_tasks:
            break
        print('TASK %d: %s %s %s %s %s' % (task, eval_type, src, c1, c2, c3))
        subres = res[res['task'] == task]
        report_agreement(subres)
        print()


def report_system_comparison(db):
    print('SYSTEM COMPARISON')
    print('=================')
    print()

    cur = db.cursor()
    cur.execute('''select eval_type, src.name, c1.name, c2.name, judgment, count(*)
                from tasks, judgments_noiaa as j, corpora as src, corpora as c1, corpora as c2
                where tasks.id=j.task_id and src.id=tasks.source
                and c1.id=j.corpus1 and c2.id=j.corpus2
                group by eval_type, source, j.corpus1, j.corpus2, judgment
                order by eval_type, source, j.corpus1, j.corpus2, judgment''')
    res = pandas.DataFrame(cur.fetchall(), columns=['eval_type', 'corpus', 'sys1', 'sys2', 'judgment', 'cnt'])

    print('Adequacy:')
    print()
    print('General corpus:')
    compare_for_corpus(res, 'Adequacy', 'general/source.txt')
    print()
    print('Discourse corpus:')
    compare_for_corpus(res, 'Adequacy', 'discourse/source.txt')
    print()
    print()
    print('Fluency:')
    print()
    print('General corpus:')
    compare_for_corpus(res, 'Fluency', 'general/source.txt')
    print()
    print('Discourse corpus:')
    compare_for_corpus(res, 'Fluency', 'discourse/source.txt')


def compare_for_corpus(res, eval_type, corpus):
    subset = res[(res['eval_type'] == eval_type) & (res['corpus'] == corpus)]

    discord = subset[subset['judgment'] != 0].copy()
    jg2 = discord['judgment'] == 2
    discord.loc[jg2, ['sys1', 'sys2']] = discord.loc[jg2, ['sys2', 'sys1']].values
    discord = discord.drop(columns=['corpus', 'eval_type', 'judgment'])
    discord = discord.rename(columns={'sys1': 'winner', 'sys2': 'loser'})

    tab = discord.pivot_table(values='cnt', index='loser', columns='winner', fill_value=0)
    print(tab)
    print()

    print("Liddell's test for pairwise comparisons")
    for s1, s2 in [(0, 1), (0, 2), (1, 2)]:
        sys1 = tab.index[s1]
        sys2 = tab.index[s2]
        s1_wins = tab.values[s2, s1]
        s2_wins = tab.values[s1, s2]

        if s1_wins < s2_wins:
            cmp = '<'
        elif s1_wins > s2_wins:
            cmp = '>'
        else:
            cmp = '='

        p, f = liddell(s1_wins, s2_wins)
        print('%s %s %s (F = %g, p = %g)' % (sys1, cmp, sys2, f, p))


def liddell(r, s):
    # F.D.K. Liddell. Simplified Exact Analysis of Case-Referent Studies: Matched Pairs; Dichotomous Exposure.
    # Journal of Epidemiology and Community Health, 37 (1983) 1, pp. 82-84.

    if r < s:
        r, s = s, r

    f = r / (s + 1)
    df1 = 2 * (s + 1)
    df2 = 2 * r

    p = 2.0 * (1.0 - scipy.stats.f.cdf(f, df1, df2))

    return p, f


if __name__ == '__main__':
    main()
