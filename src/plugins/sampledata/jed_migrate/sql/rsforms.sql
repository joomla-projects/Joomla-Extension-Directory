-- Flatten the RSForms submissions into one staging table per form. These were the VEL and abandonware report
-- forms. com_vel has been removed from the package, so nothing consumes these tables yet - they are kept as
-- staging data for the abandonware component.
DROP TABLE IF EXISTS old_rsform5;
DROP TABLE IF EXISTS old_rsform7;
DROP TABLE IF EXISTS old_rsform9;
DROP TABLE IF EXISTS old_rsform10;
DROP TABLE IF EXISTS old_rsform11;
DROP TABLE IF EXISTS old_rsform12;
DROP TABLE IF EXISTS old_rsform13;
DROP TABLE IF EXISTS old_rsform14;

-- Create structures to hold each form type
CREATE TABLE old_rsform5 (   SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, FullName TEXT, Email TEXT, Organization TEXT, passtodev TEXT, vulnType TEXT, vulExtnName TEXT, vulExtnVer TEXT, vulTemName TEXT, vulTemVer TEXT, exploittype TEXT, OtherExploitDescription TEXT, vuldescription TEXT, vulTools TEXT, IsActiveExploit TEXT, exploitInPublic TEXT, publicExploitUrl TEXT, vuiImpact TEXT, devradioselect TEXT, updateURL TEXT, devname TEXT, devEmail TEXT, jedurl TEXT, trackdb TEXT, trackDbID TEXT, devAddInfo TEXT, Downloadurl TEXT, consentuse TEXT, ReferenceNumber TEXT, formId TEXT);
CREATE TABLE old_rsform7 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, fn TEXT, updateco TEXT, uMail TEXT, uExtension_ TEXT, oldvers TEXT, UDetails TEXT, newvers TEXT, UpdateNoticeURL TEXT, changelog TEXT, Download_url_ TEXT, consentuse TEXT, formId TEXT);
CREATE TABLE old_rsform10 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, FullName TEXT, Email TEXT, Organization TEXT, vulJoomVer TEXT, vulJoomVersion TEXT, vul3ptyExt TEXT, vulExtnName TEXT, vulExtnVer TEXT, vul3ptyTem TEXT, vulTemName TEXT, vulTemVer TEXT, exploit_type TEXT, vuldescription TEXT, vulTools TEXT, IsActiveExploit TEXT, exploitInPublic TEXT, publicExploitUrl TEXT, vuiImpact TEXT, devradioselect TEXT, updateURL TEXT, devname TEXT, devEmail TEXT, trackdb TEXT, trackDbID TEXT, devAddInfo TEXT, securitycode TEXT, Submit TEXT, ReferenceNumber TEXT, formId TEXT, fileup TEXT, exploittype TEXT, OtherExploitDescription TEXT, jedurl TEXT, Downloadurl TEXT);
CREATE TABLE old_rsform11 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, FullName TEXT, Email TEXT, Organization TEXT, vulnType TEXT, vulJoomVersion TEXT, vulExtnName TEXT, vulExtnVer TEXT, vulTemName TEXT, vulTemVer TEXT, exploittype TEXT, OtherExploitDescription TEXT, vuldescription TEXT, vulTools TEXT, IsActiveExploit TEXT, exploitInPublic TEXT, publicExploitUrl TEXT, vuiImpact TEXT, devradioselect TEXT, updateURL TEXT, devname TEXT, devEmail TEXT, jedurl TEXT, trackdb TEXT, trackDbID TEXT, devAddInfo TEXT, Downloadurl TEXT, ReferenceNumber TEXT, formId TEXT, passtodev TEXT, consentuse TEXT, v3 TEXT);
CREATE TABLE old_rsform12 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, New_File TEXT, fn TEXT, updateco TEXT, uMail TEXT, uExtension_ TEXT, Extension_Update_Details TEXT, UpdateNotice_URL TEXT, codebox TEXT, Update_confirmation TEXT, formId TEXT, UpdateNoticeURL TEXT, UDetails TEXT, oldvers TEXT, newvers TEXT, changelog TEXT, tf2 TEXT, Download_url_ TEXT, consentuse TEXT, v3 TEXT);
CREATE TABLE old_rsform13 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, NAME TEXT, Email TEXT, website TEXT, experience TEXT, extensiondev TEXT, jedurl TEXT, socialinks TEXT, fname TEXT, vollink TEXT, summary TEXT, warning TEXT, reasons TEXT, Send TEXT, formId TEXT, warn TEXT, possible TEXT, consentuse TEXT);
CREATE TABLE old_rsform14 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, NAME TEXT, Email TEXT, Extension_name TEXT, Developer TEXT, Last_known_version_number TEXT, url TEXT, Reason TEXT, Send TEXT, formId TEXT, consentuse TEXT, captcha TEXT);
CREATE TABLE old_rsform9 (  SubmissionId INT NOT NULL, DateSubmitted DATETIME, UserIP VARCHAR (255), Username VARCHAR (255), UserId TEXT, NAME TEXT, Email TEXT, Extension_name TEXT, Developer TEXT, Last_known_version_number TEXT, url TEXT, Reason TEXT, consentuse TEXT, formId TEXT);

-- Grab Submission Id info from submissions
INSERT INTO old_rsform5 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=5;
INSERT INTO old_rsform7 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=7;
INSERT INTO old_rsform9 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=9;
INSERT INTO old_rsform10 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=10;
INSERT INTO old_rsform11 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=11;
INSERT INTO old_rsform12 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=12;
INSERT INTO old_rsform13 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=13;
INSERT INTO old_rsform14 (SubmissionId,DateSubmitted,UserIP,Username,UserId) SELECT DISTINCT SubmissionId,DateSubmitted,UserIP,Username,UserId FROM wqyh6_rsform_submissions WHERE FormId=14;

-- Convert RSForm rows of data into columns
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.FullName= v.FieldValue WHERE v.FieldName='FullName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.Organization= v.FieldValue WHERE v.FieldName='Organization' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.passtodev= v.FieldValue WHERE v.FieldName='passtodev' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulnType= v.FieldValue WHERE v.FieldName='vulnType' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulExtnName= v.FieldValue WHERE v.FieldName='vulExtnName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulExtnVer= v.FieldValue WHERE v.FieldName='vulExtnVer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulTemName= v.FieldValue WHERE v.FieldName='vulTemName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulTemVer= v.FieldValue WHERE v.FieldName='vulTemVer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.exploittype= v.FieldValue WHERE v.FieldName='exploittype' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.OtherExploitDescription= v.FieldValue WHERE v.FieldName='OtherExploitDescription' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vuldescription= v.FieldValue WHERE v.FieldName='vuldescription' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vulTools= v.FieldValue WHERE v.FieldName='vulTools' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.IsActiveExploit= v.FieldValue WHERE v.FieldName='IsActiveExploit' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.exploitInPublic= v.FieldValue WHERE v.FieldName='exploitInPublic' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.publicExploitUrl= v.FieldValue WHERE v.FieldName='publicExploitUrl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.vuiImpact= v.FieldValue WHERE v.FieldName='vuiImpact' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.devradioselect= v.FieldValue WHERE v.FieldName='devradioselect' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.updateURL= v.FieldValue WHERE v.FieldName='updateURL' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.devname= v.FieldValue WHERE v.FieldName='devname' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.devEmail= v.FieldValue WHERE v.FieldName='devEmail' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.jedurl= v.FieldValue WHERE v.FieldName='jedurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.trackdb= v.FieldValue WHERE v.FieldName='trackdb' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.trackDbID= v.FieldValue WHERE v.FieldName='trackDbID' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.devAddInfo= v.FieldValue WHERE v.FieldName='devAddInfo' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.Downloadurl= v.FieldValue WHERE v.FieldName='Downloadurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.consentuse= v.FieldValue WHERE v.FieldName='consentuse' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.ReferenceNumber= v.FieldValue WHERE v.FieldName='ReferenceNumber' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform5 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.fn= v.FieldValue WHERE v.FieldName='fn' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.updateco= v.FieldValue WHERE v.FieldName='updateco' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.uMail= v.FieldValue WHERE v.FieldName='uMail' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.uExtension_= v.FieldValue WHERE v.FieldName='uExtension_' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.oldvers= v.FieldValue WHERE v.FieldName='oldvers' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.UDetails= v.FieldValue WHERE v.FieldName='UDetails' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.newvers= v.FieldValue WHERE v.FieldName='newvers' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.UpdateNoticeURL= v.FieldValue WHERE v.FieldName='UpdateNoticeURL' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.changelog= v.FieldValue WHERE v.FieldName='changelog' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.Download_url_= v.FieldValue WHERE v.FieldName='Download_url_' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.consentuse= v.FieldValue WHERE v.FieldName='consentuse' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform7 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Name= v.FieldValue WHERE v.FieldName='Name' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
-- The RSForm field is named with spaces, not underscores. Mapping it to 'Extension_name'
-- matched nothing, so this column was empty in all 60 abandonware submissions - the one field
-- that says what the report is about. Same for the version below.
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Extension_name= v.FieldValue WHERE v.FieldName='Extension name' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Developer= v.FieldValue WHERE v.FieldName='Developer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Last_known_version_number= v.FieldValue WHERE v.FieldName='Last known version number' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.url= v.FieldValue WHERE v.FieldName='url' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.Reason= v.FieldValue WHERE v.FieldName='Reason' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.consentuse= v.FieldValue WHERE v.FieldName='consentuse' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform9 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.FullName= v.FieldValue WHERE v.FieldName='FullName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.vulExtnName= v.FieldValue WHERE v.FieldName='vulExtnName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.vulExtnVer= v.FieldValue WHERE v.FieldName='vulExtnVer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.vuldescription= v.FieldValue WHERE v.FieldName='vuldescription' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.devname= v.FieldValue WHERE v.FieldName='devname' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.devEmail= v.FieldValue WHERE v.FieldName='devEmail' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.jedurl= v.FieldValue WHERE v.FieldName='jedurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.Downloadurl= v.FieldValue WHERE v.FieldName='Downloadurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.ReferenceNumber= v.FieldValue WHERE v.FieldName='ReferenceNumber' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform10 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.FullName= v.FieldValue WHERE v.FieldName='FullName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.vulExtnName= v.FieldValue WHERE v.FieldName='vulExtnName' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.vulExtnVer= v.FieldValue WHERE v.FieldName='vulExtnVer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.vuldescription= v.FieldValue WHERE v.FieldName='vuldescription' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.devname= v.FieldValue WHERE v.FieldName='devname' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.devEmail= v.FieldValue WHERE v.FieldName='devEmail' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.jedurl= v.FieldValue WHERE v.FieldName='jedurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.Downloadurl= v.FieldValue WHERE v.FieldName='Downloadurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.ReferenceNumber= v.FieldValue WHERE v.FieldName='ReferenceNumber' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform11 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.fn= v.FieldValue WHERE v.FieldName='fn' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.uMail= v.FieldValue WHERE v.FieldName='uMail' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.uExtension_= v.FieldValue WHERE v.FieldName='uExtension_' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.oldvers= v.FieldValue WHERE v.FieldName='oldvers' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.newvers= v.FieldValue WHERE v.FieldName='newvers' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.UDetails= v.FieldValue WHERE v.FieldName='UDetails' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.changelog= v.FieldValue WHERE v.FieldName='changelog' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.Download_url_= v.FieldValue WHERE v.FieldName='Download_url_' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform12 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.Name= v.FieldValue WHERE v.FieldName='Name' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.website= v.FieldValue WHERE v.FieldName='website' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.jedurl= v.FieldValue WHERE v.FieldName='jedurl' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.summary= v.FieldValue WHERE v.FieldName='summary' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform13 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Name= v.FieldValue WHERE v.FieldName='Name' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Email= v.FieldValue WHERE v.FieldName='Email' AND v.SubmissionId=f.SubmissionId;
-- The RSForm field is named with spaces, not underscores. Mapping it to 'Extension_name'
-- matched nothing, so this column was empty in all 60 abandonware submissions - the one field
-- that says what the report is about. Same for the version below.
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Extension_name= v.FieldValue WHERE v.FieldName='Extension name' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Developer= v.FieldValue WHERE v.FieldName='Developer' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Last_known_version_number= v.FieldValue WHERE v.FieldName='Last known version number' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.url= v.FieldValue WHERE v.FieldName='url' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.Reason= v.FieldValue WHERE v.FieldName='Reason' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.consentuse= v.FieldValue WHERE v.FieldName='consentuse' AND v.SubmissionId=f.SubmissionId;
UPDATE old_rsform14 f, wqyh6_rsform_submission_values v SET f.formId= v.FieldValue WHERE v.FieldName='formId' AND v.SubmissionId=f.SubmissionId;
