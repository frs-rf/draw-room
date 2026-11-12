const fs = require('fs');
const { neon } = require('@neondatabase/serverless');

const envText = fs.readFileSync('.env.local', 'utf8');
const match = envText.match(/DATABASE_URL="([^"]+)"/);
if (!match) {
  console.error('DATABASE_URL not found');
  process.exit(1);
}
const sql = neon(match[1]);

async function runE2E() {
  console.log('=== Starting End-to-End Test with Neon Postgres ===');

  // 1. Test Query Participants (Viewer Page)
  console.log('1. Testing participants query...');
  const participants = await sql`
    SELECT id, name, role, sort, active 
    FROM participants 
    WHERE active = true 
    ORDER BY sort ASC;
  `;
  console.log(`   ✓ Found ${participants.length} active participants.`);

  // 2. Test Query Excluded People
  console.log('2. Testing excluded people query...');
  const excluded = await sql`
    SELECT id, "group", name, sort 
    FROM excluded_people 
    ORDER BY sort ASC;
  `;
  console.log(`   ✓ Found ${excluded.length} excluded people.`);

  // 3. Test Draw Simulation (Start Draw)
  console.log('3. Testing draw start insertion...');
  const now = new Date().toISOString();
  const testCaps = JSON.stringify([4, 4, 4, 4, 4, 4, 4, 4, 4, 4]);
  const testLeadRooms = JSON.stringify([0, 2, 4, 6, 8]);
  const testSteps = JSON.stringify([
    { index: 0, person: { id: 1, name: 'Riski Nurcahyadi', role: 'pm' }, room: 0, slot: 0 },
    { index: 1, person: { id: 2, name: 'Rahmat Hidayat', role: 'pm' }, room: 2, slot: 0 }
  ]);

  const insertedDraw = await sql`
    INSERT INTO draws (status, room_size, caps, lead_rooms, steps, current_index, created_at, updated_at)
    VALUES ('running', 4, ${testCaps}::jsonb, ${testLeadRooms}::jsonb, ${testSteps}::jsonb, 0, ${now}, ${now})
    RETURNING id, status, room_size, current_index;
  `;
  const drawId = insertedDraw[0].id;
  console.log(`   ✓ Draw created with ID: ${drawId}, status: ${insertedDraw[0].status}`);

  // 4. Test Draw Step Advance
  console.log('4. Testing draw step advance...');
  const updatedDraw = await sql`
    UPDATE draws 
    SET current_index = 1, updated_at = ${new Date().toISOString()}
    WHERE id = ${drawId}
    RETURNING id, current_index;
  `;
  console.log(`   ✓ Draw ${drawId} advanced to index: ${updatedDraw[0].current_index}`);

  // 5. Clean up test draw
  console.log('5. Cleaning up test draw...');
  await sql`DELETE FROM draws WHERE id = ${drawId};`;
  console.log('   ✓ Test draw cleaned up successfully.');

  console.log('\n=== All Neon Postgres End-to-End Tests Passed! ===');
}

runE2E().catch(err => {
  console.error('E2E Test Failed:', err);
  process.exit(1);
});
