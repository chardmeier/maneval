import pandas
import sqlite3


def main():
    dbfile = '/Users/christianhardmeier/Documents/project/2020-Chaojun/maneval-complete.db'
    db = sqlite3.connect(dbfile)
    cur = db.cursor()

    query = '''select src.corpus, src.line, src.orderid, src.sentence, sys1.sentence, sys2.sentence
            from sentences as src,
                 sentences as sys1,
                 sentences as sys2,
                 judgments_noiaa as jdg
            where corpus1 = :corpus1
              and corpus2 = :corpus2
              and task_id in (2, 4, 5, 7)
              and judgment = :judgment
              and src.corpus = :srccorpus
              and sys1.corpus = corpus1
              and sys2.corpus = corpus2
              and src.line = jdg.line
              and sys1.line = jdg.line
              and sys2.line = jdg.line
              and src.orderid = sys1.orderid
              and src.orderid = sys2.orderid
            order by src.orderid'''

    cases = [{'corpus1': c1, 'corpus2': c2, 'judgment': j, 'srccorpus': src, 'nexamples': n}
             for src, c1, c2, j, n in [(1, 3, 4, 1, 8),
                                       (1, 3, 4, 2, 13),
                                       (5, 7, 8, 1, 2),
                                       (5, 7, 8, 2, 9)]]

    print('''<!DOCTYPE html>
        <html>
        <head><title></title></head>
        <body>''')
    for c in cases:
        cur.execute(query, c)
        df = pandas.DataFrame(cur.fetchall(), columns=['corpus', 'line', 'orderid', 'src', 'sys1', 'sys2'])
        o = [df[df['orderid'] == i].drop(columns='orderid').rename(
            columns={k: '%s-%d' % (k, i) for k in ['src', 'sys1', 'sys2']})
            for i in range(4)]
        snts = o[0].set_index(['corpus', 'line'])
        for x in o[1:]:
            snts = snts.join(x.set_index(['corpus', 'line']))
        smpl = snts.sample(n=c['nexamples'])

        for (corpus, line), s in smpl.iterrows():
            print('<h1>{corpus}-{line}</h1>'.format(corpus=corpus, line=line))
            print('<table>')
            for i in range(4):
                fstr = '<tr><td>{src-%d}</td><td>{sys1-%d}</td><td>{sys2-%d}</td>' % (i, i, i)
                print(fstr.format(**s.to_dict()))
            print('</table>')

    print('</body>\n</html>')


if __name__ == '__main__':
    main()
