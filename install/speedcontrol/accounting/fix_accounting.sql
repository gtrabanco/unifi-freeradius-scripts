
--
-- Base de datos: `radius`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `radacctperiod`
--

CREATE TABLE `radacctperiod` (
  `radacctperiodid` bigint(21) NOT NULL,
  `startperiod` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `username` varchar(64) NOT NULL DEFAULT '',
  `realm` varchar(64) DEFAULT '',
  `acctupdatetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acctsessiontime` int(12) UNSIGNED DEFAULT NULL,
  `acctinputoctets` bigint(20) DEFAULT NULL,
  `acctoutputoctets` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `radacctperiod`
  ADD UNIQUE KEY `radacctperiodid` (`radacctperiodid`);

ALTER TABLE `radacctperiod`
  MODIFY `radacctperiodid` bigint(21) NOT NULL AUTO_INCREMENT;


##### Previous was generated with phpmyadmin, here begins the custom part

#
# Author: Gabriel Trabanco Llano <gtrabanco@fwok.org> // gabi.io
#
# This SQL solves the situation in FreeRadius accounting for
# larger sessions than the accounting period
#

#
# Calculate the start date of the week
#
# If the week for you start on sunday, rest one day to final result
#
DELIMITER //
CREATE OR REPLACE FUNCTION FIRST_DAY_OF_WEEK(day DATE)
RETURNS DATE DETERMINISTIC
BEGIN
  RETURN SUBDATE(day, WEEKDAY(day));
END;
//
DELIMITER ;

#
# Calculate the end date of the week
#
# If the week for you end on saturday, rest one day to the final result
#
DELIMITER //
CREATE OR REPLACE FUNCTION END_DAY_OF_WEEK(day DATE)
RETURNS DATE DETERMINISTIC
BEGIN
  RETURN SUBDATE(day, WEEKDAY(day)-6);
END;
//
DELIMITER ;


#
# This function return the current or given timestamp billing period
# depending if you want an hourly, daily, weekly, monthly
# or yearly period
#
# Returns a TIMESTAMP type
#   Example to test it:
#       SELECT GET_TIMESTAMP_PERIOD('hourly', NOW());
#
#
DELIMITER //
CREATE OR REPLACE FUNCTION GET_TIMESTAMP_PERIOD(
    Reset_Period VARCHAR(7),
    T TIMESTAMP
) RETURNS TIMESTAMP
    DETERMINISTIC
    BEGIN
        DECLARE Period_Format VARCHAR(20);
        DECLARE Period_Timestamp TIMESTAMP;

        CASE  LOWER(Reset_Period)
            WHEN 'yearly' THEN
                SET Period_Format = '%Y-01-01';
            WHEN 'monthly' THEN
                SET Period_Format = '%Y-%m-01';
            WHEN 'hourly' THEN
                SET Period_Format = '%Y-%m-%d %H:00:00';
            ELSE
                SET Period_Format = '%Y-%m-%d';
        END CASE;

        SELECT TIME_FORMAT(T, Period_Format) INTO Period_Timestamp;

        IF (Reset_Period = 'weekly') THEN
            SELECT FIRST_DAY_OF_WEEK(Period_Timestamp) INTO Period_Timestamp;
        END IF;

        RETURN Period_Timestamp;
    END;
//
DELIMITER ;


#
# This procedure check if the user has any row for billing period
# if not make an insert and if yes update it with the input values
#
# Important! The values should be those to insert or, in update ,
# the value to add in acctinputoctets, acctoutputoctets and
# acctsessiontime
#
# To avoid missunderstandings I added prefix input_ to the procedure
# params.
#
DELIMITER //
CREATE OR REPLACE PROCEDURE INSERT_OR_UPDATE_PERIOD_BILLING(
    IN input_reset_period VARCHAR(7),
    IN input_username VARCHAR(64),
    IN input_realm VARCHAR(64),
    IN input_acctupdatetime DATETIME,
    IN input_acctsessiontime INT(12) UNSIGNED,
    IN input_acctinputoctets BIGINT(20),
    IN input_acctoutputoctets BIGINT(20))
    BEGIN

        # Variables we will need
        DECLARE Billing_Period_Exists INT(1);
        DECLARE Billing_Period_Time TIMESTAMP;
        DECLARE Previous_acctsessiontime INT(12);
        DECLARE Previous_acctinputoctets BIGINT(20);
        DECLARE Previous_acctoutputoctets BIGINT(20);


        # The given billing period 
        SET Billing_Period_Time = GET_TIMESTAMP_PERIOD(
                                        input_reset_period,
                                        input_acctupdatetime);

        # Check if there is any row for this user and billing period 
        SELECT COUNT(*), acctsessiontime,
            acctinputoctets, acctoutputoctets
            INTO Billing_Period_Exists, Previous_acctsessiontime,
                Previous_acctinputoctets, Previous_acctoutputoctets
            FROM radacctperiod
            WHERE 
                startperiod >= Billing_Period_Time
                AND username = input_username;
        
        IF (Billing_Period_Exists = 1) THEN
            # There is a row for current Billing period 
            # and user so it must be an update

            UPDATE radacctperiod SET
                acctupdatetime = input_acctupdatetime,
                acctinputoctets = Previous_acctinputoctets + input_acctinputoctets,
                acctoutputoctets = Previous_acctoutputoctets + input_acctoutputoctets,
                acctsessiontime = Previous_acctsessiontime + input_acctsessiontime
                WHERE username = input_username AND startperiod = Billing_Period_Time;
        ELSE
            # We must get previous
            # It is an insert
            INSERT INTO radacctperiod (startperiod, username, realm,
                acctupdatetime, acctsessiontime, acctinputoctets,
                acctoutputoctets)
            VALUES (Billing_Period_Time, input_username, input_realm,
                input_acctupdatetime, input_acctsessiontime, input_acctinputoctets,
                input_acctoutputoctets );
        END IF;


    END;
//
DELIMITER ;


DELIMITER //
CREATE TRIGGER after_radacct_insert
    AFTER INSERT ON radius.radacct
    FOR EACH ROW
    BEGIN

        CALL INSERT_OR_UPDATE_PERIOD_BILLING(
                'hourly',
                NEW.username,
                NEW.realm,
                NEW.acctupdatetime,
                NEW.acctsessiontime,
                NEW.acctinputoctets,
                NEW.acctoutputoctets);

    END //

DELIMITER ;



DELIMITER //
CREATE TRIGGER before_radacct_update
    BEFORE UPDATE ON radius.radacct
    FOR EACH ROW
    BEGIN

        CALL INSERT_OR_UPDATE_PERIOD_BILLING(
                'hourly',
                NEW.username,
                NEW.realm,
                NEW.acctupdatetime,
                ABS(NEW.acctsessiontime - OLD.acctsessiontime),
                ABS(NEW.acctinputoctets - OLD.acctinputoctets),
                ABS(NEW.acctoutputoctets - OLD.acctoutputoctets));

    END //

DELIMITER ;


