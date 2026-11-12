const fs = require('fs');
const { neon } = require('@neondatabase/serverless');

const envText = fs.readFileSync('.env.local', 'utf8');
const match = envText.match(/DATABASE_URL="([^"]+)"/);
if (!match) {
  console.error('DATABASE_URL not found in .env.local');
  process.exit(1);
}
const sql = neon(match[1]);

async function migrate() {
  console.log('--- Step 1: Creating Tables on Neon PostgreSQL ---');

  // Migrations table
  await sql`
    CREATE TABLE IF NOT EXISTS migrations (
      id SERIAL PRIMARY KEY,
      migration VARCHAR(255) NOT NULL,
      batch INTEGER NOT NULL
    );
  `;

  // Users table
  await sql`
    CREATE TABLE IF NOT EXISTS users (
      id BIGSERIAL PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      username VARCHAR(30) UNIQUE,
      email VARCHAR(255) UNIQUE,
      email_verified_at TIMESTAMP,
      password VARCHAR(255) NOT NULL,
      remember_token VARCHAR(100),
      created_at TIMESTAMP,
      updated_at TIMESTAMP
    );
  `;

  // Password reset tokens
  await sql`
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
      email VARCHAR(255) PRIMARY KEY,
      token VARCHAR(255) NOT NULL,
      created_at TIMESTAMP
    );
  `;

  // Sessions table
  await sql`
    CREATE TABLE IF NOT EXISTS sessions (
      id VARCHAR(255) PRIMARY KEY,
      user_id BIGINT,
      ip_address VARCHAR(45),
      user_agent TEXT,
      payload TEXT NOT NULL,
      last_activity INTEGER NOT NULL
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);`;
  await sql`CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);`;

  // Cache & Cache locks
  await sql`
    CREATE TABLE IF NOT EXISTS cache (
      key VARCHAR(255) PRIMARY KEY,
      value TEXT NOT NULL,
      expiration INTEGER NOT NULL
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);`;

  await sql`
    CREATE TABLE IF NOT EXISTS cache_locks (
      key VARCHAR(255) PRIMARY KEY,
      owner VARCHAR(255) NOT NULL,
      expiration INTEGER NOT NULL
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks (expiration);`;

  // Jobs, job batches, failed jobs
  await sql`
    CREATE TABLE IF NOT EXISTS jobs (
      id BIGSERIAL PRIMARY KEY,
      queue VARCHAR(255) NOT NULL,
      payload TEXT NOT NULL,
      attempts SMALLINT NOT NULL,
      reserved_at INTEGER,
      available_at INTEGER NOT NULL,
      created_at INTEGER NOT NULL
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue);`;

  await sql`
    CREATE TABLE IF NOT EXISTS job_batches (
      id VARCHAR(255) PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      total_jobs INTEGER NOT NULL,
      pending_jobs INTEGER NOT NULL,
      failed_jobs INTEGER NOT NULL,
      failed_job_ids TEXT NOT NULL,
      options TEXT,
      cancelled_at INTEGER,
      created_at INTEGER NOT NULL,
      finished_at INTEGER
    );
  `;

  await sql`
    CREATE TABLE IF NOT EXISTS failed_jobs (
      id BIGSERIAL PRIMARY KEY,
      uuid VARCHAR(255) UNIQUE NOT NULL,
      connection TEXT NOT NULL,
      queue TEXT NOT NULL,
      payload TEXT NOT NULL,
      exception TEXT NOT NULL,
      failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL
    );
  `;

  // Participants table
  await sql`
    CREATE TABLE IF NOT EXISTS participants (
      id BIGSERIAL PRIMARY KEY,
      name VARCHAR(255) NOT NULL,
      role VARCHAR(8) NOT NULL,
      sort SMALLINT DEFAULT 0 NOT NULL,
      active BOOLEAN DEFAULT TRUE NOT NULL,
      created_at TIMESTAMP,
      updated_at TIMESTAMP
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS participants_role_index ON participants (role);`;

  // Excluded people table
  await sql`
    CREATE TABLE IF NOT EXISTS excluded_people (
      id BIGSERIAL PRIMARY KEY,
      "group" VARCHAR(255) NOT NULL,
      name VARCHAR(255) NOT NULL,
      sort SMALLINT DEFAULT 0 NOT NULL,
      created_at TIMESTAMP,
      updated_at TIMESTAMP
    );
  `;

  // Draws table
  await sql`
    CREATE TABLE IF NOT EXISTS draws (
      id BIGSERIAL PRIMARY KEY,
      status VARCHAR(12) DEFAULT 'running' NOT NULL,
      room_size SMALLINT NOT NULL,
      caps JSONB NOT NULL,
      lead_rooms JSONB NOT NULL,
      steps JSONB NOT NULL,
      current_index SMALLINT DEFAULT 0 NOT NULL,
      started_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
      finished_at TIMESTAMP,
      created_at TIMESTAMP,
      updated_at TIMESTAMP
    );
  `;
  await sql`CREATE INDEX IF NOT EXISTS draws_status_index ON draws (status);`;

  console.log('✓ Tables created successfully');

  // Record migrations in migrations table
  const migrationList = [
    '0001_01_01_000000_create_users_table',
    '0001_01_01_000001_create_cache_table',
    '0001_01_01_000002_create_jobs_table',
    '2026_11_11_000001_create_participants_table',
    '2026_11_11_000002_create_draws_table',
    '2026_11_11_000003_add_username_to_users',
  ];

  for (const m of migrationList) {
    const existing = await sql`SELECT id FROM migrations WHERE migration = ${m};`;
    if (!existing.length) {
      await sql`INSERT INTO migrations (migration, batch) VALUES (${m}, 1);`;
    }
  }
  console.log('✓ Migration tracking records registered');

  // Seed participants if table is empty
  const countRes = await sql`SELECT COUNT(*)::int as count FROM participants;`;
  if (countRes[0].count === 0) {
    console.log('--- Step 2: Seeding Participants ---');
    const data = {
      pm: [
        'Riski Nurcahyadi', 'Rahmat Hidayat', 'Muhammad Noer', 'Faizal Akbar Alfaridzi',
        'Asep Hasanudin', 'Yusep Teja Purnama', 'Muhammad Daffa Zaid Haritsah', 'Muhamad Fazri Irmansyah',
      ],
      sm: ['Erik Dani Apriadi', 'Dede Dian Komarudin'],
      atm: ['Joko Agus Rianto Ambarita'],
      prg: [
        'Daud Rifki Gunawan', 'Andika Muhammad Rezka', 'Arham Dadiamar', 'Yasin Jundi Habulloh',
        'Japar Sidik', 'Muhammad Reza', 'Sani Sanjaya', 'Muhammad Rezon Maulana',
        'Rohman Nurwahidin', 'Aldian Fallahakbar Nuriza', 'Bintang Rizky Sulistyo',
        'Nirwan Ramdani', 'Muhamad Abyan Ma’rup',
      ],
      imp: [
        'Budi Prasetio', 'Fazrie Alfikrie', 'Agvien Syauqi Fadhlurrahman', 'Sandi Rali',
        'Adhit Octavian', 'Yudi Pratama', 'Sabda Arya Putra', 'Muhamad Luthfy Noor',
        'Muhammad Fakhrijal Pratama', 'Rian Ibnu Rizal', 'Moch Safe’i Eka Gunawan',
        'Rico Miraj Irawan', 'Farhan Fathia Andriawan',
        'M. Reva Rusyda Andita', 'Mohamad Iqbal Indrayana', 'Martin Solehudin Mujib',
      ],
      bb: ['Irshad'],
    };

    const now = new Date().toISOString();
    for (const [role, names] of Object.entries(data)) {
      for (let i = 0; i < names.length; i++) {
        await sql`
          INSERT INTO participants (name, role, sort, active, created_at, updated_at)
          VALUES (${names[i]}, ${role}, ${i}, true, ${now}, ${now});
        `;
      }
    }

    const out = {
      'Driver & Backbone': ['Arinda · Driver', 'Yudi · Driver', 'Pak Eko · Backbone'],
      'Panitia (tidak ikut diundi)': [
        'Fadhil Rafi Fauzan', 'Rizal Naufal Ridwan', 'Muhamad Faris Rafi', 'Muhammad Yusuf Firdaus',
        'Anjastian Suryatul Akbar', 'Magie Adytia', 'Noufal Fadhillah Setiadi', 'Edgar David Deswan',
      ],
      'Tidak ikut gathering': [
        'Wiki Saputra', 'Roby Harlen Setiadi', 'Arinda Bagaswara',
        'Muhammad Yahya Firdaus', 'Pak Eko', 'Mochamad Fikri Prayogi',
      ],
    };

    let sort = 0;
    for (const [group, names] of Object.entries(out)) {
      for (const name of names) {
        await sql`
          INSERT INTO excluded_people ("group", name, sort, created_at, updated_at)
          VALUES (${group}, ${name}, ${sort++}, ${now}, ${now});
        `;
      }
    }
    console.log('✓ Participants and excluded people seeded');
  } else {
    console.log(`✓ Participants already exist (${countRes[0].count} records)`);
  }

  // Seed default panitia user (username: panitia, password: password123)
  const userRes = await sql`SELECT id FROM users WHERE username = 'panitia';`;
  if (!userRes.length) {
    const now = new Date().toISOString();
    // bcrypt hash of "password" with rounds 12
    const passHash = '$2y$12$IZJHpTGGFOOmqbGKx1ZNau4aoM4T3koz2aEjl5OgZUmfSs.SKd3wq';
    await sql`
      INSERT INTO users (name, username, email, password, created_at, updated_at)
      VALUES ('Panitia Draw', 'panitia', 'panitia@panitia.local', ${passHash}, ${now}, ${now});
    `;
    console.log('✓ Default panitia account created (username: panitia)');
  }

  // Verify
  console.log('--- Step 3: Verifying Database Content ---');
  const participantCount = await sql`SELECT COUNT(*)::int as count FROM participants;`;
  const excludedCount = await sql`SELECT COUNT(*)::int as count FROM excluded_people;`;
  const usersCount = await sql`SELECT COUNT(*)::int as count FROM users;`;
  const sampleParticipants = await sql`SELECT id, name, role FROM participants LIMIT 5;`;

  console.log({
    participants: participantCount[0].count,
    excluded_people: excludedCount[0].count,
    users: usersCount[0].count,
    sample: sampleParticipants,
  });

  console.log('✅ Neon PostgreSQL successfully initialized and verified end to end!');
}

migrate().catch((err) => {
  console.error('Migration failed:', err);
  process.exit(1);
});
