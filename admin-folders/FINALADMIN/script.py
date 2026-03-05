import re

filepath = r'../FINALADMIN/ADMIN-settings.HTML'
with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

# Profile
content = content.replace('<input\n                      class="form-input" type="text" value="Admin" placeholder="First name">', '<input class="form-input" id="profile_first_name" type="text" placeholder="First name">')
content = content.replace('<input class="form-input"\n                      type="text" value="" placeholder="Last name">', '<input class="form-input" id="profile_last_name" type="text" placeholder="Last name">')
content = content.replace('<input class="form-input" type="email"\n                      value="admin@aluminumlady.com" placeholder="Email">', '<input class="form-input" id="profile_email" type="email" placeholder="Email">')
content = content.replace('<input class="form-input"\n                      type="text" value="+86 757 8888 0000" placeholder="+country code">', '<input class="form-input" id="profile_phone" type="text" placeholder="+country code">')
content = content.replace('<input class="form-input"\n                      type="text" value="Super Administrator" placeholder="e.g. Marketing Manager">', '<input class="form-input" id="profile_role" type="text" placeholder="e.g. Marketing Manager">')
content = content.replace('<input class="form-input"\n                      type="text" value="Management" placeholder="e.g. Operations">', '<input class="form-input" id="profile_department" type="text" placeholder="e.g. Operations">')
content = content.replace('<textarea class="form-textarea"\n                      placeholder="Short bio for public team page…"></textarea>', '<textarea class="form-textarea" id="profile_bio" placeholder="Short bio for public team page…"></textarea>')

# Site Info
content = content.replace('<input class="form-input" type="text"\n                      value="Aluminum Lady Building Materials Co., Ltd.">', '<input class="form-input" id="site_company_name" type="text">')
content = content.replace('<input class="form-input"\n                      type="text" value="Premium Aluminum Solutions for Modern Architecture"\n                      placeholder="Short tagline">', '<input class="form-input" id="site_tagline" type="text" placeholder="Short tagline">')
content = content.replace('<input class="form-input" type="email"\n                      value="info@aluminumlady.com">', '<input class="form-input" id="site_contact_email" type="email">')
content = content.replace('<input class="form-input"\n                      type="text" value="+86 757 8283 3000">', '<input class="form-input" id="site_contact_phone" type="text">')
content = content.replace('<input\n                      class="form-input" type="text" value="No. 8 Aluminum Boulevard, Foshan, Guangdong, China 528000">\n                  </div>', '<input class="form-input" id="site_company_address" type="text">\n                  </div>')
content = content.replace('<input class="form-input"\n                      type="text" value="https://www.aluminumlady.com">', '<input class="form-input" id="site_website_url" type="text">')
content = content.replace('<input class="form-input"\n                      type="text" value="Mon–Sat 8:00 AM – 6:00 PM (GMT+8)">', '<input class="form-input" id="site_business_hours" type="text">')
content = content.replace('<textarea\n                      class="form-textarea"\n                      style="min-height:100px">Aluminum Lady is a leading manufacturer of premium aluminum building systems, specializing in curtain walls, windows, doors, and architectural facades for commercial and residential projects across Asia.</textarea>', '<textarea class="form-textarea" id="site_company_description" style="min-height:100px"></textarea>')

content = content.replace('<input class="form-input"\n                      type="text" value="https://facebook.com/aluminumlady" placeholder="https://">', '<input class="form-input" id="site_facebook_url" type="text" placeholder="https://">')
content = content.replace('<input class="form-input"\n                      type="text" value="" placeholder="https://">', '<input class="form-input" id="site_instagram_url" type="text" placeholder="https://">')
content = content.replace('<input class="form-input"\n                      type="text" value="AluminumLady_Official" placeholder="WeChat ID">', '<input class="form-input" id="site_wechat_id" type="text" placeholder="WeChat ID">')
content = content.replace('<input class="form-input"\n                      type="text" value="+86 138 0000 0000" placeholder="+country code">', '<input class="form-input" id="site_whatsapp_number" type="text" placeholder="+country code">')

# Appearance handled by ID in replace block

with open(filepath, 'w', encoding='utf-8') as f:
    f.write(content)
print('Done script.')
