<?php

namespace Database\Seeders;

/**
 * Fixed demo data (fictional companies). Amounts in cents.
 */
final class DemoCatalog
{
    /**
     * @return array<string, array{0: string, 1: list<array{0: string, 1: int, 2: int, 3: int}>}>
     *                                                                                            code => [category, [name, cost, price, min stock]]
     */
    public static function products(): array
    {
        return [
            'FUR' => ['Furniture', [
                ['Ergonomic office chair', 8900, 18900, 6],
                ['Electric standing desk', 24000, 45900, 4],
                ['Conference table 8 seats', 38000, 72000, 2],
                ['Steel filing cabinet', 9500, 17900, 4],
                ['Five-shelf bookcase', 6200, 12900, 3],
                ['Stackable visitor chair', 3100, 6900, 10],
                ['Dual monitor arm', 4200, 8900, 6],
                ['Adjustable footrest', 1500, 3900, 8],
                ['LED desk lamp', 1900, 4500, 10],
                ['Magnetic whiteboard 120x90', 5400, 11900, 3],
            ]],
            'ELE' => ['Electronics', [
                ['27" 4K monitor', 21000, 38900, 5],
                ['24" Full HD monitor', 9800, 17900, 8],
                ['Wireless keyboard', 2600, 5900, 12],
                ['Wireless mouse', 1400, 3400, 15],
                ['USB-C docking station', 7800, 15900, 6],
                ['1080p webcam', 3900, 8900, 8],
                ['Noise-cancelling headset', 8200, 16900, 6],
                ['Laptop 14" i5 16GB', 62000, 98900, 3],
                ['Laptop 16" i7 32GB', 98000, 154900, 2],
                ['Portable SSD 1TB', 6500, 12900, 8],
            ]],
            'NET' => ['Networking', [
                ['Wi-Fi 6 router', 11500, 21900, 4],
                ['24-port gigabit switch', 16800, 29900, 3],
                ['8-port gigabit switch', 3200, 6900, 6],
                ['Cat6 patch cable 3m', 250, 790, 40],
                ['Cat6 patch cable 10m', 600, 1690, 25],
                ['Ceiling access point', 9400, 17900, 4],
                ['12U network rack', 21000, 38900, 2],
                ['24-port patch panel', 4100, 8900, 3],
                ['UPS 1500VA', 17500, 31900, 3],
                ['Surge protector 8 outlets', 1800, 4200, 10],
            ]],
            'SUP' => ['Office supplies', [
                ['Copy paper A4, box of 5 reams', 2100, 3900, 20],
                ['Ballpoint pens, box of 50', 700, 1690, 15],
                ['Sticky notes, 12 pads', 450, 1190, 20],
                ['Heavy-duty stapler', 1200, 2690, 6],
                ['Staples, box of 5000', 180, 490, 30],
                ['Hardcover notebook A5', 350, 990, 25],
                ['Black toner cartridge', 5200, 9900, 8],
                ['Color toner set', 14800, 26900, 4],
                ['Binder clips, 48 pack', 380, 990, 20],
                ['Bamboo desk organizer', 1300, 2990, 8],
            ]],
            'STO' => ['Storage & archiving', [
                ['Archive boxes, pack of 10', 1600, 3490, 12],
                ['Thermal label printer', 11200, 19900, 3],
                ['Shipping labels, 500 roll', 900, 2190, 15],
                ['Cross-cut paper shredder', 13500, 24900, 3],
                ['Fireproof document safe', 18900, 34900, 2],
                ['Duplex document scanner', 24500, 42900, 2],
                ['A3 laminator', 5900, 11900, 3],
                ['Guillotine paper cutter', 4700, 9900, 3],
                ['10-drawer storage cart', 7300, 14900, 4],
                ['Wall key cabinet', 3400, 7400, 4],
            ]],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}> [name, city, email]
     */
    public static function customers(): array
    {
        return [
            ['Andes Logistics', 'Denver', 'purchasing@andeslogistics.test'],
            ['Blue Harbor Foods', 'Seattle', 'ops@blueharbor.test'],
            ['Cobalt Engineering', 'Houston', 'admin@cobalteng.test'],
            ['Delta Health Clinics', 'Atlanta', 'it@deltahealth.test'],
            ['Evergreen Schools', 'Portland', 'facilities@evergreen.test'],
            ['Falcon Legal Partners', 'Chicago', 'office@falconlegal.test'],
            ['Granite Construction Co.', 'Phoenix', 'procurement@granite.test'],
            ['Horizon Travel', 'Miami', 'finance@horizontravel.test'],
            ['Ironwood Manufacturing', 'Detroit', 'buying@ironwood.test'],
            ['Juniper Design Studio', 'Austin', 'studio@juniper.test'],
            ['Keystone Accounting', 'Philadelphia', 'hello@keystone.test'],
            ['Lumen Energy', 'San Diego', 'supply@lumenenergy.test'],
            ['Maple Dental Group', 'Minneapolis', 'admin@mapledental.test'],
            ['Nimbus Cloud Services', 'San Jose', 'it@nimbus.test'],
            ['Orion Media', 'Los Angeles', 'office@orionmedia.test'],
            ['Pioneer Insurance', 'Dallas', 'facilities@pioneer.test'],
            ['Quantum Labs', 'Boston', 'lab@quantumlabs.test'],
            ['Riverside Hotels', 'Nashville', 'purchasing@riverside.test'],
            ['Summit Fitness', 'Salt Lake City', 'gyms@summitfit.test'],
            ['Terra Farms Co-op', 'Sacramento', 'coop@terrafarms.test'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> category code => [name, contact, email]
     */
    public static function suppliers(): array
    {
        return [
            'FUR' => ['Prime Furniture Makers', 'Grace Kim', 'sales@primefurniture.test'],
            'ELE' => ['BrightLine Electronics', 'Omar Haddad', 'orders@brightline.test'],
            'NET' => ['NetCore Wholesale', 'Priya Nair', 'b2b@netcore.test'],
            'SUP' => ['Global Office Supply', 'Tom Becker', 'orders@globaloffice.test'],
            'STO' => ['Archive Solutions Inc.', 'Lucía Fernández', 'sales@archivesolutions.test'],
            'ALT1' => ['TechSource Distribution', 'Ken Watanabe', 'sales@techsource.test'],
            'ALT2' => ['PaperWorks Ltd.', 'Anna Schulz', 'orders@paperworks.test'],
            'ALT3' => ['Digital Depot', 'Marcus Lee', 'b2b@digitaldepot.test'],
            'ALT4' => ['Atlas Hardware', 'Sara Rossi', 'sales@atlashardware.test'],
            'ALT5' => ['Metro Logistics Supply', 'Ivan Petrov', 'orders@metrosupply.test'],
        ];
    }
}
