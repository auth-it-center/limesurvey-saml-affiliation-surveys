# limesurvey-saml-affiliation-surveys
Limesurvey plugin to filter users per survey based on SAML affiliation attribute.

## Requirements
* LimeSurvey 3.XX
* [SAML-Plugin](https://github.com/auth-it-center/Limesurvey-SAML-Authentication)
* (optional) [SAML-Person-Status](https://github.com/auth-it-center/limesurvey-saml-person-status)

## Installation instructions
1. Copy **SAMLAffiliationPermit** folder with its content at **limesurvey/plugins** folder
2. Go to **Admin > Configuration > Plugin Manager** or **https:/example.com/index.php/admin/pluginmanager/sa/index**
and **Enable** the plugin

## How to enable plugin for specific survey
1. Go to **Surveys > (Select desired survey) > Simple Plugins** or
**https:/example.com/index.php/admin/survey/sa/rendersidemenulink/surveyid/{survey_id}/subaction/plugins**
2. Open **Settings for plugin AuthSurvey** accordion
3. Click **Enabled** checkbox
4. Open **Settings for plugin SAMLAffiliationPermit** accordion
5. Click **Enabled** checkbox

![Plugin settings](images/plugin_settings.png)

## Configuration options

### Global
* **SAML Attribute** Global SAML attribute to filter users with. Can be modified in every survey.

### Plugin
* **Enabled** If checked then the plugin is enabled for the selected survey
* **SAML Attribute** SAML attribute to filter users with for current survey
* **Allowed Affiliations** Comma separated list of the allowed affiliations that are allowed to participate on the survey
* **Allowed Status per Affiliation** Comma separated list of the allowed person status per affiliation that are allowed to participate on the survey. Allowed values are:
  * **active**: only active persons can participate
  * **inactive**: only inactive person can participate
  * **whatever**: everyone can participate
  * **missing**: only persons that their status field is missing can participate

### Config examples

Active Students, faculty members and active staff can participate
* **Affiliations**: student,faculty,staff
* **Status**: active,whatever

Only active students can participate
* **Affiliations**: student
* **Status**: active


## Images
![Global Plugin settings](images/global_settings.png)
