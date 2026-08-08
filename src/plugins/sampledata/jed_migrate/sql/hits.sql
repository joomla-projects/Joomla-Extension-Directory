-- Carry the legacy hit history over as an aggregate (P1-12 item 7).
--
-- Into #__jed_hit_stats and never into #__jed_hit_log. 7.5 asks for "aggregated at minimum,
-- otherwise the browse lists break", and the aggregate is all that is wanted here: the legacy raw
-- rows carry `ip_address char(45)` in clear, and importing 2.1 million addresses the JED has no
-- use for - and would then have to keep, protect and eventually erase - to arrive at numbers that
-- can be computed from them directly would be indefensible.
--
-- Only the daily totals cross over. The one column that cannot be reconstructed is
-- download_clicks: JED3 logged a single kind of hit with no type at all, so every legacy row is a
-- view. Download clicks begin at zero and start accumulating from the first day the new button is
-- live, which is honest and cannot be helped.
--
-- Robots are counted out of `views` and into `robot_hits`, exactly as the daily job does, so the
-- imported days and the days after go-live mean the same thing. JED3 marked 264,484 of 2,158,587
-- rows as robots (12.3%); its `suspicious` column exists and was never set, so it contributes
-- nothing and is not read here.
INSERT INTO `#__jed_hit_stats` (`extension_id`, `period`, `views`, `download_clicks`, `robot_hits`)
SELECT h.extension_id,
       DATE(h.created_time),
       SUM(CASE WHEN h.is_robot = 0 THEN 1 ELSE 0 END),
       0,
       SUM(CASE WHEN h.is_robot = 1 THEN 1 ELSE 0 END)
FROM wqyh6_jed_hit_log h
-- Only hits for listings that made it across, which drops two kinds of row. The larger by far is
-- `extension_id = 0` - 406,584 of the 2,158,587 rows measured, hits the legacy system recorded
-- without attaching them to any listing at all. The rest belong to extensions that have since
-- been deleted. Neither has anywhere to go: the primary key here is (extension_id, period).
INNER JOIN `#__jed_extensions` e ON e.id = h.extension_id
GROUP BY h.extension_id, DATE(h.created_time)
ON DUPLICATE KEY UPDATE
    `views`      = VALUES(`views`),
    `robot_hits` = VALUES(`robot_hits`);
