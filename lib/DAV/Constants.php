<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Constants
{
    public const T_ACCOUNTS = 'awm_accounts';
    public const T_PRINCIPALS = 'adav_principals';
    public const T_GROUPMEMBERS = 'adav_groupmembers';

    public const T_CALENDARS = 'adav_calendars';
    public const T_CALENDARCHANGES = 'adav_calendarchanges';
    public const T_CALENDAROBJECTS = 'adav_calendarobjects';
    public const T_SCHEDULINGOBJECTS = 'adav_schedulingobjects';
    public const T_CALENDARSUBSCRIPTIONS = 'adav_calendarsubscriptions';
    public const T_REMINDERS = 'adav_reminders';
    public const T_CALENDARINSTANCES = 'adav_calendarinstances';

    public const T_ADDRESSBOOKS = 'adav_addressbooks';
    public const T_ADDRESSBOOKCHANGES = 'adav_addressbookchanges';
    public const T_CARDS = 'adav_cards';

    public const T_LOCKS = 'adav_locks';
    public const T_CACHE = 'adav_cache';

    public const GLOBAL_CONTACTS = 'Global Contacts';
    public const CALENDAR_DEFAULT_NAME = 'My calendar';
    public const CALENDAR_DEFAULT_COLOR = '#f09650';
    public const CALENDAR_DEFAULT_UUID = 'MyCalendar';

    public const TASKS_DEFAULT_NAME = 'My tasks';
    public const TASKS_DEFAULT_COLOR = '#f68987';

    public const ADDRESSBOOK_DEFAULT_NAME_OLD = 'Default';
    public const ADDRESSBOOK_DEFAULT_DISPLAY_NAME_OLD = 'General';

    public const ADDRESSBOOK_DEFAULT_NAME = 'AddressBook';
    public const ADDRESSBOOK_DEFAULT_DISPLAY_NAME = 'Address Book';
    public const ADDRESSBOOK_COLLECTED_NAME = 'Collected';
    public const ADDRESSBOOK_COLLECTED_DISPLAY_NAME = 'Collected Addresses';
    public const ADDRESSBOOK_SHARED_WITH_ALL_NAME = 'SharedWithAll';
    public const ADDRESSBOOK_SHARED_WITH_ALL_DISPLAY_NAME = 'Shared With All';
    public const ADDRESSBOOK_TEAM_NAME = 'gab';
    public const ADDRESSBOOK_TEAM_DISPLAY_NAME = 'Team';

    public const DAV_PUBLIC_PRINCIPAL = 'caldav_public_user@localhost';
    public const DAV_TENANT_PRINCIPAL = 'dav_tenant_user@localhost';

    public const DAV_USER_AGENT = 'eMClient/8.2.1659.0';
    public const DAV_SERVER_NAME = 'AfterlogicDAVServer';

    public const FILESTORAGE_PRIVATE_QUOTA = 104857600;
    public const FILESTORAGE_CORPORATE_QUOTA = 1048576000;

    public const FILESTORAGE_PATH_ROOT = '/files';
    public const FILESTORAGE_PATH_PERSONAL = '/private';
    public const FILESTORAGE_PATH_CORPORATE = '/corporate';
    public const FILESTORAGE_PATH_SHARED = '/shared';

    public const PRINCIPALS_PREFIX = 'principals/';
}
