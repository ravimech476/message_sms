<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">home</i>
            Support Home
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.support') }}">Introduction</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('dashboard') }}">Main Dashboard</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('customer.link.redirect', ['username' => $user->uname]) }}" target="_blank">Campaign
                    Manager</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="https://www.sms.expert/" target="_blank">SMS Expert Home Page</a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">http</i>
            Sending SMS (HTTP)
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.sendingsms') }}">Send Outbound SMS</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.receivingdlrs') }}">Receive Delivery Receipts</a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">settings_ethernet</i>
            Sending SMS (SMPP)
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="javascript:;">Please Call For Details</a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">inbox</i>
            Receiving SMS
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.receivingsms') }}">Receive Inbound SMS</a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">account_balance_wallet</i>
            Wallet Balances
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.wholesalewalletcheck') }}">SMS + Keyword Balances</a>
            </li>
        </ul>
    </div>

    <div class="sidebar-section">
        <h3 class="sidebar-title">
            <i class="material-icons-outlined">vpn_key</i>
            Keyword Tools
        </h3>
        <ul class="sidebar-list">
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keywordwhois') }}">Keyword Availability</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keywordregistration') }}">Register Keyword</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keywordsetforwardings') }}">Set Keyword Forwarding</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.listkeywords') }}">List Keywords</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keywordrenewal') }}">Renew Keyword</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keyworddeletion') }}">Delete Keyword</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.keywordreplacement') }}">Replace Keyword</a>
            </li>
            <li>
                <span class="sidebar-arrow">→</span>
                <a href="{{ route('technical.wholesaleapiresponsecodes') }}">Tool Response Codes</a>
            </li>
        </ul>
    </div>
</div>
