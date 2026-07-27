import mysql, { Connection, RowDataPacket } from 'mysql2/promise';

export const LANDING_FIXTURE = {
  opensAt: 1_685_664_000,
  closesAt: 1_893_456_000,
  awardsAt: 1_698_890_400,
  winnerReleaseAt: 1_698_899_400,
} as const;

const ARCHIVE_SUFFIX = '2025';
const ARCHIVE_TABLES = [
  `baseline_special_best_info_${ARCHIVE_SUFFIX}`,
  `baseline_judging_scores_bos_${ARCHIVE_SUFFIX}`,
  `baseline_style_types_${ARCHIVE_SUFFIX}`,
  `baseline_judging_scores_${ARCHIVE_SUFFIX}`,
  `baseline_judging_tables_${ARCHIVE_SUFFIX}`,
  `baseline_brewing_${ARCHIVE_SUFFIX}`,
  `baseline_brewer_${ARCHIVE_SUFFIX}`,
] as const;

const db = () => mysql.createConnection({
  host: process.env.E2E_DB_HOST ?? '127.0.0.1',
  port: Number(process.env.E2E_DB_PORT ?? 3306),
  user: process.env.E2E_DB_USER ?? 'bcoem',
  password: process.env.E2E_DB_PASSWORD ?? 'bcoem_password',
  database: process.env.E2E_DB_NAME ?? 'bcoem',
});

async function withDb(operation: (connection: Connection) => Promise<void>): Promise<void> {
  const connection = await db();
  try {
    await operation(connection);
  } finally {
    await connection.end();
  }
}

export async function setLandingWindows(
  registration: [number, number],
  entries: [number, number],
  judges: [number, number],
): Promise<void> {
  await withDb(async connection => {
    await connection.execute(
      `UPDATE baseline_contest_info
       SET contestRegistrationOpen = ?, contestRegistrationDeadline = ?,
           contestEntryOpen = ?, contestEntryDeadline = ?,
           contestJudgeOpen = ?, contestJudgeDeadline = ?
       WHERE id = 1`,
      [...registration, ...entries, ...judges],
    );
  });
}

export async function setLandingCapacityPreferences(
  entryLimit: number | null,
  paidEntryLimit: number | null,
  entries: number,
  paidEntries: number,
): Promise<void> {
  if (entries < 0 || paidEntries < 0 || paidEntries > entries) {
    throw new Error('Landing capacity fixture counts are invalid.');
  }

  await withDb(async connection => {
    await connection.beginTransaction();
    try {
      await connection.execute(
        `UPDATE baseline_preferences
         SET prefsEntryLimit = ?, prefsEntryLimitPaid = ?
         WHERE id = 1`,
        [entryLimit, paidEntryLimit],
      );
      await connection.execute('DELETE FROM baseline_brewing');
      for (let index = 0; index < entries; index += 1) {
        await connection.execute(
          `INSERT INTO baseline_brewing (brewName, brewPaid)
           VALUES (?, ?)`,
          [`E2E landing capacity ${index + 1}`, index < paidEntries ? 1 : 0],
        );
      }
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    }
  });
}

export async function setLandingWinnerDisplay(
  display: boolean,
  releaseAt: number,
  judging: [number, number] | null,
): Promise<void> {
  await withDb(async connection => {
    await connection.beginTransaction();
    try {
      await connection.execute(
        `UPDATE baseline_preferences
         SET prefsDisplayWinners = ?, prefsWinnerDelay = ?
         WHERE id = 1`,
        [display ? 'Y' : 'N', releaseAt],
      );
      await connection.execute('DELETE FROM baseline_judging_locations');
      if (judging !== null) {
        await connection.execute(
          `INSERT INTO baseline_judging_locations
             (judgingLocType, judgingDate, judgingDateEnd, judgingLocName)
           VALUES (?, ?, ?, ?)`,
          [0, judging[0], judging[1], 'E2E Landing Judging'],
        );
      }
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    }
  });
}

export async function setLandingOptionalDates(
  dropoff: [number | null, number | null],
  shipping: [number | null, number | null],
  awardsAt: number | null,
): Promise<void> {
  await withDb(async connection => {
    await connection.execute(
      `UPDATE baseline_contest_info
       SET contestDropoffOpen = ?, contestDropoffDeadline = ?,
           contestShippingOpen = ?, contestShippingDeadline = ?,
           contestAwardsLocTime = ?
       WHERE id = 1`,
      [...dropoff, ...shipping, awardsAt],
    );
  });
}

export async function setLandingSponsors(enabled: boolean, present: boolean): Promise<void> {
  await withDb(async connection => {
    await connection.beginTransaction();
    try {
      await connection.execute(
        `UPDATE baseline_preferences
         SET prefsSponsors = ?, prefsSponsorLogos = ?
         WHERE id = 1`,
        [enabled ? 'Y' : 'N', enabled ? 'Y' : 'N'],
      );
      await connection.execute('DELETE FROM baseline_sponsors');
      if (present) {
        await connection.execute(
          `INSERT INTO baseline_sponsors
             (sponsorName, sponsorURL, sponsorImage, sponsorText, sponsorLocation, sponsorLevel, sponsorEnable)
           VALUES (?, ?, ?, ?, ?, ?, ?)`,
          [
            'E2E Accessible Sponsor',
            'https://example.test/sponsor',
            '/images/misc-cropped-bottles_3000x500.jpg',
            'Supports the competition.',
            'Chicago, IL',
            1,
            1,
          ],
        );
      }
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    }
  });
}

export async function setLandingContacts(present: boolean): Promise<void> {
  await withDb(async connection => {
    await connection.beginTransaction();
    try {
      await connection.execute('DELETE FROM baseline_contacts');
      if (present) {
        await connection.execute(
          `INSERT INTO baseline_contacts
             (contactFirstName, contactLastName, contactPosition, contactEmail)
           VALUES (?, ?, ?, ?)`,
          ['E2E', 'Coordinator', 'Competition Coordinator', 'coordinator@example.test'],
        );
      }
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    }
  });
}

export async function setLandingArchives(present: boolean): Promise<void> {
  await withDb(async connection => {
    await connection.execute('DELETE FROM baseline_archive');
    for (const table of ARCHIVE_TABLES) {
      await connection.query(`DROP TABLE IF EXISTS \`${table}\``);
    }
    if (!present) {
      return;
    }

    const [styles] = await connection.execute<RowDataPacket[]>(
      `SELECT id, brewStyleGroup, brewStyleNum, brewStyle
       FROM baseline_styles
       WHERE brewStyleActive = ?
       ORDER BY id
       LIMIT 1`,
      ['Y'],
    );
    const style = styles[0];
    if (style === undefined) {
      throw new Error('The landing archive fixture requires one active baseline style.');
    }

    await connection.query(
      `CREATE TABLE baseline_brewer_${ARCHIVE_SUFFIX} LIKE baseline_brewer`,
    );
    await connection.query(
      `CREATE TABLE baseline_brewing_${ARCHIVE_SUFFIX} LIKE baseline_brewing`,
    );
    await connection.query(
      `CREATE TABLE baseline_judging_tables_${ARCHIVE_SUFFIX} LIKE baseline_judging_tables`,
    );
    await connection.query(
      `CREATE TABLE baseline_judging_scores_${ARCHIVE_SUFFIX} LIKE baseline_judging_scores`,
    );
    await connection.query(
      `CREATE TABLE baseline_style_types_${ARCHIVE_SUFFIX} LIKE baseline_style_types`,
    );
    await connection.query(
      `CREATE TABLE baseline_judging_scores_bos_${ARCHIVE_SUFFIX} LIKE baseline_judging_scores_bos`,
    );
    await connection.query(
      `CREATE TABLE baseline_special_best_info_${ARCHIVE_SUFFIX} LIKE baseline_special_best_info`,
    );
    await connection.query(
      `INSERT INTO baseline_style_types_${ARCHIVE_SUFFIX}
       SELECT * FROM baseline_style_types`,
    );

    await connection.execute(
      `INSERT INTO baseline_brewer_${ARCHIVE_SUFFIX}
         (id, uid, brewerFirstName, brewerLastName, brewerClubs)
       VALUES (?, ?, ?, ?, ?)`,
      [1, 7_001, 'Archive', 'Winner', 'E2E Brewers'],
    );
    await connection.execute(
      `INSERT INTO baseline_brewing_${ARCHIVE_SUFFIX}
         (id, brewName, brewStyle, brewCategory, brewCategorySort, brewSubCategory,
          brewBrewerID, brewReceived)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        1,
        'Archive Fixture Ale',
        style.brewStyle,
        style.brewStyleGroup,
        style.brewStyleGroup,
        style.brewStyleNum,
        7_001,
        1,
      ],
    );
    await connection.execute(
      `INSERT INTO baseline_judging_tables_${ARCHIVE_SUFFIX}
         (id, tableName, tableStyles, tableNumber)
       VALUES (?, ?, ?, ?)`,
      [1, 'Archive Fixture Table', String(style.id), 1],
    );
    await connection.execute(
      `INSERT INTO baseline_judging_scores_${ARCHIVE_SUFFIX}
         (eid, bid, scoreTable, scoreEntry, scorePlace, scoreType)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [1, 7_001, 1, 42, 1, 1],
    );
    await connection.execute(
      `INSERT INTO baseline_archive
         (archiveStyleSet, archiveSuffix, archiveWinnerMethod, archiveDisplayWinners)
       VALUES (?, ?, ?, ?)`,
      ['BJCP2021', ARCHIVE_SUFFIX, 0, 'Y'],
    );
  });
}

export async function resetLandingFixtures(): Promise<void> {
  await withDb(async connection => {
    for (const table of ARCHIVE_TABLES) {
      await connection.query(`DROP TABLE IF EXISTS \`${table}\``);
    }
    await connection.beginTransaction();
    try {
      await connection.execute(
        `UPDATE baseline_contest_info
         SET contestRegistrationOpen = ?, contestRegistrationDeadline = ?,
             contestEntryOpen = ?, contestEntryDeadline = ?,
             contestJudgeOpen = ?, contestJudgeDeadline = ?,
             contestDropoffOpen = ?, contestDropoffDeadline = ?,
             contestShippingOpen = ?, contestShippingDeadline = ?,
             contestAwardsLocTime = ?
         WHERE id = 1`,
        [
          LANDING_FIXTURE.opensAt,
          LANDING_FIXTURE.closesAt,
          LANDING_FIXTURE.opensAt,
          LANDING_FIXTURE.closesAt,
          LANDING_FIXTURE.opensAt,
          LANDING_FIXTURE.closesAt,
          LANDING_FIXTURE.opensAt,
          LANDING_FIXTURE.closesAt,
          LANDING_FIXTURE.opensAt,
          LANDING_FIXTURE.closesAt,
          LANDING_FIXTURE.awardsAt,
        ],
      );
      await connection.execute(
        `UPDATE baseline_preferences
         SET prefsCAPTCHA = ?, prefsSponsors = ?, prefsSponsorLogos = ?,
             prefsContact = ?, prefsEntryLimit = ?, prefsEntryLimitPaid = ?,
             prefsDisplayWinners = ?, prefsWinnerDelay = ?
         WHERE id = 1`,
        [0, 'N', 'N', 'N', null, null, 'N', LANDING_FIXTURE.winnerReleaseAt],
      );
      await connection.execute('DELETE FROM baseline_brewing');
      await connection.execute('DELETE FROM baseline_judging_locations');
      await connection.execute('DELETE FROM baseline_sponsors');
      await connection.execute('DELETE FROM baseline_archive');
      await connection.execute('DELETE FROM baseline_contacts');
      await connection.execute(
        `INSERT INTO baseline_contacts
           (contactFirstName, contactLastName, contactPosition, contactEmail)
         VALUES (?, ?, ?, ?)`,
        ['Default', 'Admin', 'Competition Coordinator', 'user.baseline@brewingcompetitions.com'],
      );
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    }
  });
}
