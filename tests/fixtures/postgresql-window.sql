--
-- Window function statements taken from the PostgreSQL regression test suite.
--
-- Source:  src/test/regress/sql/window.sql
--          https://github.com/postgres/postgres  (fetched 2026-09-19)
-- License: PostgreSQL License (BSD-like), Copyright (c) 1996-2025, PostgreSQL
--          Global Development Group
--
-- The statements were extracted mechanically: every statement mentioning OVER
-- or WINDOW, reduced to those which use syntax this parser targets. Statements
-- using PostgreSQL specific syntax (::casts, FILTER, WITHIN GROUP, generate_series,
-- set returning functions, DDL, EXPLAIN, plpgsql bodies) are not part of this file.
--
-- Some statements are rejected by PostgreSQL for semantic reasons, for example a
-- window function within WHERE. They are kept on purpose: this parser does not
-- validate, so it has to parse and recreate them anyway. One statement which
-- PostgreSQL reports as a *syntax* error is left out.
--
-- One statement per line, comments start with "--".
--
SELECT depname, empno, salary, sum(salary) OVER (PARTITION BY depname) FROM empsalary ORDER BY depname, salary
SELECT depname, empno, salary, rank() OVER (PARTITION BY depname ORDER BY salary) FROM empsalary
SELECT four, ten, SUM(SUM(four)) OVER (PARTITION BY four), AVG(ten) FROM tenk1 GROUP BY four, ten ORDER BY four, ten
SELECT depname, empno, salary, sum(salary) OVER w FROM empsalary WINDOW w AS (PARTITION BY depname)
SELECT depname, empno, salary, rank() OVER w FROM empsalary WINDOW w AS (PARTITION BY depname ORDER BY salary) ORDER BY rank() OVER w
SELECT COUNT(*) OVER () FROM tenk1 WHERE unique2 < 10
SELECT COUNT(*) OVER w FROM tenk1 WHERE unique2 < 10 WINDOW w AS ()
SELECT four FROM tenk1 WHERE FALSE WINDOW w AS (PARTITION BY ten)
SELECT sum(four) OVER (PARTITION BY ten ORDER BY unique2) AS sum_1, ten, four FROM tenk1 WHERE unique2 < 10
SELECT row_number() OVER (ORDER BY unique2) FROM tenk1 WHERE unique2 < 10
SELECT rank() OVER (PARTITION BY four ORDER BY ten) AS rank_1, ten, four FROM tenk1 WHERE unique2 < 10
SELECT dense_rank() OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT percent_rank() OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT cume_dist() OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT ntile(3) OVER (ORDER BY ten, four), ten, four FROM tenk1 WHERE unique2 < 10
SELECT ntile(NULL) OVER (ORDER BY ten, four), ten, four FROM tenk1 LIMIT 2
SELECT lag(ten) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lag(ten, four) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lag(ten, four, 0) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lag(ten, four, 0.7) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten
SELECT lead(ten) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lead(ten * 2, 1) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lead(ten * 2, 1, -1) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT lead(ten * 2, 1, -1.4) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten
SELECT first_value(ten) OVER (PARTITION BY four ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT last_value(four) OVER (ORDER BY ten), ten, four FROM tenk1 WHERE unique2 < 10
SELECT last_value(ten) OVER (PARTITION BY four), ten, four FROM (SELECT * FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten)s ORDER BY four, ten
SELECT nth_value(ten, four + 1) OVER (PARTITION BY four), ten, four FROM (SELECT * FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten)s
SELECT ten, two, sum(hundred) AS gsum, sum(sum(hundred)) OVER (PARTITION BY two ORDER BY ten) AS wsum FROM tenk1 GROUP BY ten, two
SELECT count(*) OVER (PARTITION BY four), four FROM (SELECT * FROM tenk1 WHERE two = 1)s WHERE unique2 < 10
SELECT * FROM( SELECT count(*) OVER (PARTITION BY four ORDER BY ten) + sum(hundred) OVER (PARTITION BY two ORDER BY ten) AS total, count(*) OVER (PARTITION BY four ORDER BY ten) AS fourcount, sum(hundred) OVER (PARTITION BY two ORDER BY ten) AS twosum FROM tenk1 )sub WHERE total <> fourcount + twosum
SELECT avg(four) OVER (PARTITION BY four ORDER BY thousand / 100) FROM tenk1 WHERE unique2 < 10
SELECT ten, two, sum(hundred) AS gsum, sum(sum(hundred)) OVER win AS wsum FROM tenk1 GROUP BY ten, two WINDOW win AS (PARTITION BY two ORDER BY ten)
SELECT sum(salary), row_number() OVER (ORDER BY depname), sum(sum(salary)) OVER (ORDER BY depname DESC) FROM empsalary GROUP BY depname
SELECT sum(salary) OVER w1, count(*) OVER w2 FROM empsalary WINDOW w1 AS (ORDER BY salary), w2 AS (ORDER BY salary)
SELECT lead(ten, (SELECT two FROM tenk1 WHERE s.unique2 = unique2)) OVER (PARTITION BY four ORDER BY ten) FROM tenk1 s WHERE unique2 < 10
SELECT count(*) OVER (PARTITION BY four) FROM (SELECT * FROM tenk1 WHERE FALSE)s
SELECT sum(salary) OVER w, rank() OVER w FROM empsalary WINDOW w AS (PARTITION BY depname ORDER BY salary DESC)
SELECT empno, depname, salary, bonus, depadj, MIN(bonus) OVER (ORDER BY empno), MAX(depadj) OVER () FROM( SELECT *, CASE WHEN enroll_date < '2008-01-01' THEN 2008 - extract(YEAR FROM enroll_date) END * 500 AS bonus, CASE WHEN AVG(salary) OVER (PARTITION BY depname) < salary THEN 200 END AS depadj FROM empsalary )s
SELECT SUM(COUNT(f1)) OVER () FROM int4_tbl WHERE f1=42
select ten, sum(unique1) + sum(unique2) as res, rank() over (order by sum(unique1) + sum(unique2)) as rank from tenk1 group by ten order by ten
SELECT four, ten, sum(ten) over (partition by four order by ten), last_value(ten) over (partition by four order by ten) FROM (select distinct ten, four from tenk1) ss
SELECT four, ten, sum(ten) over (partition by four order by ten range between unbounded preceding and current row), last_value(ten) over (partition by four order by ten range between unbounded preceding and current row) FROM (select distinct ten, four from tenk1) ss
SELECT four, ten, sum(ten) over (partition by four order by ten range between unbounded preceding and unbounded following), last_value(ten) over (partition by four order by ten range between unbounded preceding and unbounded following) FROM (select distinct ten, four from tenk1) ss
SELECT four, ten/4 as two, sum(ten/4) over (partition by four order by ten/4 range between unbounded preceding and current row), last_value(ten/4) over (partition by four order by ten/4 range between unbounded preceding and current row) FROM (select distinct ten, four from tenk1) ss
SELECT four, ten/4 as two, sum(ten/4) over (partition by four order by ten/4 rows between unbounded preceding and current row), last_value(ten/4) over (partition by four order by ten/4 rows between unbounded preceding and current row) FROM (select distinct ten, four from tenk1) ss
SELECT sum(unique1) over (order by four range between current row and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between current row and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 2 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 2 following exclude no others), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 2 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 2 following exclude group), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 2 following exclude ties), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT first_value(unique1) over (ORDER BY four rows between current row and 2 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT first_value(unique1) over (ORDER BY four rows between current row and 2 following exclude group), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT first_value(unique1) over (ORDER BY four rows between current row and 2 following exclude ties), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT last_value(unique1) over (ORDER BY four rows between current row and 2 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT last_value(unique1) over (ORDER BY four rows between current row and 2 following exclude group), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT last_value(unique1) over (ORDER BY four rows between current row and 2 following exclude ties), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 2 preceding and 1 preceding), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 1 following and 3 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between unbounded preceding and 1 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (w range between current row and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10 WINDOW w AS (order by four)
SELECT sum(unique1) over (w range between unbounded preceding and current row exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10 WINDOW w AS (order by four)
SELECT sum(unique1) over (w range between unbounded preceding and current row exclude group), unique1, four FROM tenk1 WHERE unique1 < 10 WINDOW w AS (order by four)
SELECT sum(unique1) over (w range between unbounded preceding and current row exclude ties), unique1, four FROM tenk1 WHERE unique1 < 10 WINDOW w AS (order by four)
SELECT first_value(unique1) over w, nth_value(unique1, 2) over w AS nth_2, last_value(unique1) over w, unique1, four FROM tenk1 WHERE unique1 < 10 WINDOW w AS (order by four range between current row and unbounded following)
SELECT sum(unique1) over (order by unique1 rows (SELECT unique1 FROM tenk1 ORDER BY unique1 LIMIT 1) + 1 PRECEDING), unique1 FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between current row and 9223372036854775807 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between 9223372036854775807 following and 1 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT last_value(unique1) over (ORDER BY four rows between current row and 9223372036854775807 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (ORDER BY four groups between current row and 9223372036854775807 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (ORDER BY four groups between 9223372036854775807 following and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
select first_value(salary) over(order by salary range between 1000 preceding and 1000 following), lead(salary) over(order by salary range between 1000 preceding and 1000 following), nth_value(salary, 1) over(order by salary range between 1000 preceding and 1000 following), salary from empsalary
select last_value(salary) over(order by salary range between 1000 preceding and 1000 following), lag(salary) over(order by salary range between 1000 preceding and 1000 following), salary from empsalary
select first_value(salary) over(order by salary range between 1000 following and 3000 following exclude current row), lead(salary) over(order by salary range between 1000 following and 3000 following exclude ties), nth_value(salary, 1) over(order by salary range between 1000 following and 3000 following exclude ties), salary from empsalary
select last_value(salary) over(order by salary range between 1000 following and 3000 following exclude group), lag(salary) over(order by salary range between 1000 following and 3000 following exclude group), salary from empsalary
SELECT sum(unique1) over (rows between 1 preceding and 1 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between unbounded(1) preceding and unbounded(1) following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (rows between unbounded.x preceding and unbounded.x following), unique1, four FROM tenk1, (values (1)) as unbounded(x) WHERE unique1 < 10
select id, f_float4, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float4 range between 1 preceding and 1 following)
select id, f_float4, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float4 range between 'inf' preceding and 'inf' following)
select id, f_float4, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float4 range between 'inf' preceding and 'inf' preceding)
select id, f_float4, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float4 range between 'inf' following and 'inf' following)
select id, f_float4, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float4 range between 1.1 preceding and 'NaN' following)
select id, f_float8, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float8 range between 1 preceding and 1 following)
select id, f_float8, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float8 range between 'inf' preceding and 'inf' following)
select id, f_float8, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float8 range between 'inf' preceding and 'inf' preceding)
select id, f_float8, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float8 range between 'inf' following and 'inf' following)
select id, f_float8, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_float8 range between 1.1 preceding and 'NaN' following)
select id, f_numeric, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_numeric range between 1 preceding and 1 following)
select id, f_numeric, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_numeric range between 'inf' preceding and 'inf' following)
select id, f_numeric, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_numeric range between 'inf' preceding and 'inf' preceding)
select id, f_numeric, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_numeric range between 'inf' following and 'inf' following)
select id, f_numeric, first_value(id) over w, last_value(id) over w from numerics window w as (order by f_numeric range between 1.1 preceding and 'NaN' following)
select id, f_time, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_time desc range between '70 min' preceding and '2 hours' following)
select id, f_time, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_time desc range between '-70 min' preceding and '2 hours' following)
select id, f_timetz, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timetz desc range between '70 min' preceding and '2 hours' following)
select id, f_timetz, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timetz desc range between '70 min' preceding and '-2 hours' following)
select id, f_interval, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_interval desc range between '1 year' preceding and '1 year' following)
select id, f_interval, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_interval desc range between '-1 year' preceding and '1 year' following)
select id, f_timestamptz, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timestamptz desc range between '1 year' preceding and '1 year' following)
select id, f_timestamptz, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timestamptz desc range between '1 year' preceding and '-1 year' following)
select id, f_timestamp, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timestamp desc range between '1 year' preceding and '1 year' following)
select id, f_timestamp, first_value(id) over w, last_value(id) over w from datetimes window w as (order by f_timestamp desc range between '-1 year' preceding and '1 year' following)
select max(enroll_date) over (order by enroll_date range between 1 preceding and 2 following exclude ties), salary, enroll_date from empsalary
select max(enroll_date) over (order by salary range between -1 preceding and 2 following exclude ties), salary, enroll_date from empsalary
select max(enroll_date) over (order by salary range between 1 preceding and -2 following exclude ties), salary, enroll_date from empsalary
SELECT sum(unique1) over (order by four groups between unbounded preceding and current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between unbounded preceding and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between current row and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 1 preceding and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 1 following and unbounded following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between unbounded preceding and 2 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 2 preceding and 1 preceding), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 2 preceding and 1 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 0 preceding and 0 following), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 2 preceding and 1 following exclude current row), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 2 preceding and 1 following exclude group), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (order by four groups between 2 preceding and 1 following exclude ties), unique1, four FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (partition by ten order by four groups between 0 preceding and 0 following),unique1, four, ten FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (partition by ten order by four groups between 0 preceding and 0 following exclude current row), unique1, four, ten FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (partition by ten order by four groups between 0 preceding and 0 following exclude group), unique1, four, ten FROM tenk1 WHERE unique1 < 10
SELECT sum(unique1) over (partition by ten order by four groups between 0 preceding and 0 following exclude ties), unique1, four, ten FROM tenk1 WHERE unique1 < 10
select first_value(salary) over(order by enroll_date groups between 1 preceding and 1 following), lead(salary) over(order by enroll_date groups between 1 preceding and 1 following), nth_value(salary, 1) over(order by enroll_date groups between 1 preceding and 1 following), salary, enroll_date from empsalary
select last_value(salary) over(order by enroll_date groups between 1 preceding and 1 following), lag(salary) over(order by enroll_date groups between 1 preceding and 1 following), salary, enroll_date from empsalary
select first_value(salary) over(order by enroll_date groups between 1 following and 3 following exclude current row), lead(salary) over(order by enroll_date groups between 1 following and 3 following exclude ties), nth_value(salary, 1) over(order by enroll_date groups between 1 following and 3 following exclude ties), salary, enroll_date from empsalary
select last_value(salary) over(order by enroll_date groups between 1 following and 3 following exclude group), lag(salary) over(order by enroll_date groups between 1 following and 3 following exclude group), salary, enroll_date from empsalary
SELECT count(*) OVER (PARTITION BY four) FROM (SELECT * FROM tenk1 UNION ALL SELECT * FROM tenk2)s LIMIT 0
select f1, sum(f1) over (partition by f1 range between 1 preceding and 1 following) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1 order by f2 range between 1 preceding and 1 following) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1, f1 order by f2 range between 2 preceding and 1 preceding) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1, f2 order by f2 range between 1 following and 2 following) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1 groups between 1 preceding and 1 following) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1 order by f2 groups between 1 preceding and 1 following) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1, f1 order by f2 groups between 2 preceding and 1 preceding) from t1 where f1 = f2
select f1, sum(f1) over (partition by f1, f2 order by f2 groups between 1 following and 2 following) from t1 where f1 = f2
SELECT rank() OVER (ORDER BY length('abc'))
SELECT rank() OVER (ORDER BY rank() OVER (ORDER BY random()))
SELECT * FROM empsalary WHERE row_number() OVER (ORDER BY salary) < 10
SELECT * FROM empsalary INNER JOIN tenk1 ON row_number() OVER (ORDER BY salary) < 10
SELECT rank() OVER (ORDER BY 1), count(*) FROM empsalary GROUP BY 1
SELECT count(*) OVER w FROM tenk1 WINDOW w AS (ORDER BY unique1), w AS (ORDER BY unique1)
SELECT rank() OVER (PARTITION BY four, ORDER BY ten) FROM tenk1
SELECT count() OVER () FROM tenk1
SELECT ntile(0) OVER (ORDER BY ten), ten, four FROM tenk1
SELECT nth_value(four, 0) OVER (ORDER BY ten), ten, four FROM tenk1
SELECT empno, depname, row_number() OVER (PARTITION BY depname ORDER BY enroll_date) rn, rank() OVER (PARTITION BY depname ORDER BY enroll_date ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) rnk, count(*) OVER (PARTITION BY depname ORDER BY enroll_date RANGE BETWEEN CURRENT ROW AND CURRENT ROW) cnt FROM empsalary
SELECT * FROM (SELECT empno, row_number() OVER (ORDER BY empno) rn FROM empsalary) emp WHERE rn < 3
SELECT * FROM (SELECT empno, row_number() OVER (ORDER BY empno) rn FROM empsalary) emp WHERE 3 > rn
SELECT * FROM (SELECT empno, row_number() OVER (ORDER BY empno) rn FROM empsalary) emp WHERE 2 >= rn
SELECT * FROM (SELECT empno, salary, rank() OVER (ORDER BY salary DESC) r FROM empsalary) emp WHERE r <= 3
SELECT * FROM (SELECT empno, salary, dense_rank() OVER (ORDER BY salary DESC) dr FROM empsalary) emp WHERE dr = 1
SELECT * FROM (SELECT empno, salary, count(*) OVER (ORDER BY salary DESC) c FROM empsalary) emp WHERE c <= 3
SELECT * FROM (SELECT empno, salary, count(empno) OVER (ORDER BY salary DESC) c FROM empsalary) emp WHERE c <= 3
SELECT * FROM (SELECT empno, depname, row_number() OVER (PARTITION BY depname ORDER BY empno) rn FROM empsalary) emp WHERE rn < 3
SELECT * FROM (SELECT empno, depname, salary, count(empno) OVER (PARTITION BY depname ORDER BY salary DESC) c FROM empsalary) emp WHERE c <= 3
SELECT * FROM (SELECT row_number() OVER (PARTITION BY salary) AS rn, lead(depname) OVER (PARTITION BY salary) || ' Department' AS n_dep FROM empsalary) emp WHERE rn < 1
SELECT * FROM (SELECT *, count(salary) OVER (PARTITION BY depname || '') c1, row_number() OVER (PARTITION BY depname) rn, count(*) OVER (PARTITION BY depname) c2, count(*) OVER (PARTITION BY '' || depname) c3, ntile(2) OVER (PARTITION BY depname) nt FROM empsalary ) e WHERE rn <= 1 AND c1 <= 3 AND nt < 2
SELECT * FROM (SELECT depname, empno, salary, enroll_date, row_number() OVER (PARTITION BY depname ORDER BY enroll_date) AS first_emp, row_number() OVER (PARTITION BY depname ORDER BY enroll_date DESC) AS last_emp FROM empsalary) emp WHERE first_emp = 1 OR last_emp = 1
SELECT nth_value_def(n := 2, val := ten) OVER (PARTITION BY four), ten, four FROM (SELECT * FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten) s
SELECT nth_value_def(ten) OVER (PARTITION BY four), ten, four FROM (SELECT * FROM tenk1 WHERE unique2 < 10 ORDER BY four, ten) s
SELECT i,COUNT(v) OVER (ORDER BY i ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM (VALUES(1,1),(2,2),(3,NULL),(4,NULL)) t(i,v)
SELECT i,COUNT(*) OVER (ORDER BY i ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING) FROM (VALUES(1,1),(2,2),(3,NULL),(4,NULL)) t(i,v)
SELECT i, b, bool_and(b) OVER w, bool_or(b) OVER w FROM (VALUES (1,true), (2,true), (3,false), (4,false), (5,true)) v(i,b) WINDOW w AS (ORDER BY i ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING)
SELECT name, orbit, lag(orbit) OVER w AS lag, lag(orbit) RESPECT NULLS OVER w AS lag_respect, lag(orbit) IGNORE NULLS OVER w AS lag_ignore FROM planets WINDOW w AS (ORDER BY name)
SELECT name, orbit, lead(orbit) OVER w AS lead, lead(orbit) RESPECT NULLS OVER w AS lead_respect, lead(orbit) IGNORE NULLS OVER w AS lead_ignore FROM planets WINDOW w AS (ORDER BY name)
SELECT name, orbit, first_value(orbit) RESPECT NULLS OVER w1, first_value(orbit) IGNORE NULLS OVER w1, first_value(orbit) RESPECT NULLS OVER w2, first_value(orbit) IGNORE NULLS OVER w2 FROM planets WINDOW w1 AS (ORDER BY name ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING), w2 AS (ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING)
SELECT name, orbit, nth_value(orbit, 2) RESPECT NULLS OVER w1, nth_value(orbit, 2) IGNORE NULLS OVER w1, nth_value(orbit, 2) RESPECT NULLS OVER w2, nth_value(orbit, 2) IGNORE NULLS OVER w2 FROM planets WINDOW w1 AS (ORDER BY name ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING), w2 AS (ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING)
SELECT name, orbit, last_value(orbit) RESPECT NULLS OVER w1, last_value(orbit) IGNORE NULLS OVER w1, last_value(orbit) RESPECT NULLS OVER w2, last_value(orbit) IGNORE NULLS OVER w2 FROM planets WINDOW w1 AS (ORDER BY name ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING), w2 AS (ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING)
SELECT name, orbit, first_value(orbit) IGNORE NULLS OVER w, last_value(orbit) IGNORE NULLS OVER w, nth_value(orbit, 2) IGNORE NULLS OVER w, lead(orbit, 1) IGNORE NULLS OVER w AS lead_ignore, lag(orbit, 1) IGNORE NULLS OVER w AS lag_ignore FROM planets WINDOW w AS (ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING EXCLUDE CURRENT ROW)
SELECT sum(orbit) OVER () FROM planets
SELECT sum(orbit) RESPECT NULLS OVER () FROM planets
SELECT sum(orbit) IGNORE NULLS OVER () FROM planets
SELECT row_number() OVER () FROM planets
SELECT row_number() RESPECT NULLS OVER () FROM planets
SELECT row_number() IGNORE NULLS OVER () FROM planets
SELECT rank() OVER () FROM planets
SELECT rank() RESPECT NULLS OVER () FROM planets
SELECT rank() IGNORE NULLS OVER () FROM planets
SELECT dense_rank() OVER () FROM planets
SELECT dense_rank() RESPECT NULLS OVER () FROM planets
SELECT dense_rank() IGNORE NULLS OVER () FROM planets
SELECT percent_rank() OVER () FROM planets
SELECT percent_rank() RESPECT NULLS OVER () FROM planets
SELECT percent_rank() IGNORE NULLS OVER () FROM planets
SELECT cume_dist() OVER () FROM planets
SELECT cume_dist() RESPECT NULLS OVER () FROM planets
SELECT cume_dist() IGNORE NULLS OVER () FROM planets
SELECT ntile(1) OVER () FROM planets
SELECT ntile(1) RESPECT NULLS OVER () FROM planets
SELECT ntile(1) IGNORE NULLS OVER () FROM planets
SELECT name, orbit, first_value(orbit) IGNORE NULLS OVER w, last_value(orbit) IGNORE NULLS OVER w, nth_value(orbit, 2) IGNORE NULLS OVER w, lead(orbit, 1) IGNORE NULLS OVER w AS lead_ignore, lag(orbit, 1) IGNORE NULLS OVER w AS lag_ignore FROM planets WINDOW w AS (ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING)
SELECT name, distance, orbit, first_value(orbit) IGNORE NULLS OVER w, last_value(orbit) IGNORE NULLS OVER w, nth_value(orbit, 2) IGNORE NULLS OVER w, lead(orbit, 1) IGNORE NULLS OVER w AS lead_ignore, lag(orbit, 1) IGNORE NULLS OVER w AS lag_ignore FROM planets WINDOW w AS (PARTITION BY distance ORDER BY name ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING)
