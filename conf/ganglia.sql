
CREATE TABLE IF NOT EXISTS overlay_events (
    `event_id` SERIAL
  , `description` VARCHAR(250)
  , `summary` VARCHAR(250)
  , `grid` VARCHAR(100)
  , `cluster` VARCHAR(100)
  , `host_regex` VARCHAR(100)
  , `start_time` BIGINT DEFAULT NULL
  , `end_time` BIGINT DEFAULT NULL
);

