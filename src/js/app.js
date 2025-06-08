function updateTimeAndGreeting() {
  const now = new Date();
  const options = {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
  };
  
  // Update the time display
  document.getElementById('time').innerText = now.toLocaleTimeString('en-US', options);
  
  // Get current hour in 24-hour format for the Philippines timezone
  const philippinesTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
  const currentHour = philippinesTime.getHours();
  
  // Determine appropriate greeting based on time of day
  let greeting = '';
  if (currentHour >= 1 && currentHour < 12) {
      greeting = 'Good morning';
  } else if (currentHour >= 12 && currentHour < 18) {
      greeting = 'Good afternoon';
  } else {
      greeting = 'Good evening';
  }
  
  // You can customize the admin name or fetch it from your system
  const adminName = 'Admin'; // Replace with dynamic admin name if available
  
  // Update the greeting element
  document.getElementById('greeting').innerText = `${greeting}, ${adminName}!`;
}

// Update time and greeting every second
setInterval(updateTimeAndGreeting, 1000);

// Initial call to set the time and greeting immediately
updateTimeAndGreeting();

